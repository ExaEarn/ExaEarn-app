<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\SpotExecutionEvent;
use App\Models\SpotOrderBookSnapshot;
use App\Services\FinancialDecimal;
use RuntimeException;

class MatchingEngineReplayService
{
    public function __construct(private readonly OrderBookSnapshotService $snapshots)
    {
    }

    /**
     * @return array{market:string,last_sequence:int,bids:array,asks:array,open_orders:array,checksum:string,gaps:array<int,array{expected:int,actual:int}>}
     */
    public function replay(Market $market, bool $allowNoSnapshot = true): array
    {
        $snapshot = $this->latestValidSnapshot($market);
        if (!$snapshot && !$allowNoSnapshot) {
            throw new RuntimeException('No valid snapshot exists for market replay.');
        }

        $lastSequence = $snapshot ? (int) $snapshot->last_sequence : 0;
        $open = [];

        if ($snapshot) {
            foreach (($snapshot->open_orders ?? []) as $order) {
                $open[(string) $order['order_uuid']] = $order;
            }
        }

        $expected = $lastSequence + 1;
        $gaps = [];
        $events = SpotExecutionEvent::query()
            ->where('market_id', $market->id)
            ->where('sequence', '>', $lastSequence)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        foreach ($events as $event) {
            $sequence = (int) $event->sequence;
            if ($sequence !== $expected) {
                $gaps[] = ['expected' => $expected, 'actual' => $sequence];
                break;
            }
            $this->applyEvent($open, (string) $event->event_type, $event->payload ?? []);
            $lastSequence = $sequence;
            $expected = $sequence + 1;
        }

        if ($gaps !== []) {
            $market->forceFill(['trading_status' => 'halted'])->save();
            throw new RuntimeException('SEQUENCE GAP detected during replay.');
        }

        $levels = $this->levels($open);
        $checksum = hash('sha256', json_encode([
            'last_sequence' => $lastSequence,
            'bids' => $levels['bids'],
            'asks' => $levels['asks'],
        ], JSON_UNESCAPED_SLASHES));

        return [
            'market' => $market->symbol,
            'last_sequence' => $lastSequence,
            'bids' => $levels['bids'],
            'asks' => $levels['asks'],
            'open_orders' => array_values($open),
            'checksum' => $checksum,
            'gaps' => [],
        ];
    }

    public function verifyAgainstCurrentSnapshot(Market $market): bool
    {
        $snapshot = $this->snapshots->latest($market);
        if (!$snapshot) {
            return true;
        }

        $current = $this->snapshots->currentChecksum($market, (int) $snapshot->last_sequence);

        return hash_equals((string) $snapshot->checksum, $current);
    }

    private function latestValidSnapshot(Market $market): ?SpotOrderBookSnapshot
    {
        return SpotOrderBookSnapshot::query()
            ->where('market_id', $market->id)
            ->latest('last_sequence')
            ->get()
            ->first(function (SpotOrderBookSnapshot $snapshot): bool {
                return is_array($snapshot->bids)
                    && is_array($snapshot->asks)
                    && is_array($snapshot->open_orders)
                    && (string) $snapshot->checksum !== '';
            });
    }

    /**
     * @param array<string, array<string, mixed>> $open
     */
    private function applyEvent(array &$open, string $type, array $payload): void
    {
        $orderUuid = (string) ($payload['order_uuid'] ?? '');
        if ($orderUuid === '') {
            return;
        }

        if (in_array($type, ['ORDER_OPENED', 'ORDER_PARTIALLY_FILLED'], true)) {
            $open[$orderUuid] = [
                'order_uuid' => $orderUuid,
                'side' => (string) ($payload['side'] ?? ''),
                'price' => (string) ($payload['price'] ?? '0'),
                'remaining_amount' => (string) ($payload['remaining_amount'] ?? '0'),
                'sequence' => (int) ($payload['sequence'] ?? 0),
            ];
        }

        if (in_array($type, ['ORDER_FILLED', 'ORDER_CANCELLED', 'ORDER_REJECTED'], true)) {
            unset($open[$orderUuid]);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $orders
     */
    private function levels(array $orders): array
    {
        $bids = [];
        $asks = [];
        foreach ($orders as $order) {
            if (FinancialDecimal::compare((string) $order['remaining_amount'], '0') <= 0) {
                continue;
            }
            $side = $order['side'] === 'buy' ? 'bids' : 'asks';
            $price = (string) $order['price'];
            ${$side}[$price] = FinancialDecimal::add(${$side}[$price] ?? '0', (string) $order['remaining_amount']);
        }

        uksort($bids, fn (string $a, string $b): int => FinancialDecimal::compare($b, $a));
        uksort($asks, fn (string $a, string $b): int => FinancialDecimal::compare($a, $b));

        return [
            'bids' => collect($bids)->map(fn (string $amount, string $price): array => ['price' => $price, 'amount' => $amount])->values()->all(),
            'asks' => collect($asks)->map(fn (string $amount, string $price): array => ['price' => $price, 'amount' => $amount])->values()->all(),
        ];
    }
}
