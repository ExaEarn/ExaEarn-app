<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\Order;
use App\Models\OrderBook;
use App\Models\SpotOrderBookSnapshot;
use Illuminate\Support\Str;

class OrderBookSnapshotService
{
    public function create(Market $market, int $lastSequence): SpotOrderBookSnapshot
    {
        $orders = $this->openOrders($market);
        $book = $this->levelsFromOrders($orders);
        $checksum = $this->checksum($book['bids'], $book['asks'], $lastSequence);

        OrderBook::query()->updateOrCreate(
            ['market_id' => $market->id, 'pair' => $market->symbol],
            ['bid_orders' => $book['bids'], 'ask_orders' => $book['asks'], 'last_synced_at' => now()]
        );

        return SpotOrderBookSnapshot::query()->create([
            'snapshot_id' => (string) Str::uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'last_sequence' => $lastSequence,
            'bids' => $book['bids'],
            'asks' => $book['asks'],
            'open_orders' => $orders,
            'checksum' => $checksum,
        ]);
    }

    public function latest(Market $market): ?SpotOrderBookSnapshot
    {
        return SpotOrderBookSnapshot::query()
            ->where('market_id', $market->id)
            ->latest('last_sequence')
            ->first();
    }

    public function currentChecksum(Market $market, int $lastSequence): string
    {
        $book = $this->levelsFromOrders($this->openOrders($market));

        return $this->checksum($book['bids'], $book['asks'], $lastSequence);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openOrders(Market $market): array
    {
        return Order::query()
            ->where('market_id', $market->id)
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(fn (Order $order): array => [
                'order_uuid' => $order->order_uuid,
                'side' => $order->side,
                'price' => (string) $order->price,
                'remaining_amount' => (string) $order->remaining_amount,
                'sequence' => (int) $order->sequence,
            ])
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array{bids: array<int, array{price:string, amount:string}>, asks: array<int, array{price:string, amount:string}>}
     */
    private function levelsFromOrders(array $orders): array
    {
        $bids = [];
        $asks = [];
        foreach ($orders as $order) {
            $target = $order['side'] === 'buy' ? 'bids' : 'asks';
            ${$target}[(string) $order['price']] = bcadd(${$target}[(string) $order['price']] ?? '0', (string) $order['remaining_amount'], 18);
        }

        uksort($bids, fn (string $a, string $b): int => bccomp($b, $a, 18));
        uksort($asks, fn (string $a, string $b): int => bccomp($a, $b, 18));

        return [
            'bids' => collect($bids)->map(fn (string $amount, string $price): array => ['price' => $price, 'amount' => $amount])->values()->all(),
            'asks' => collect($asks)->map(fn (string $amount, string $price): array => ['price' => $price, 'amount' => $amount])->values()->all(),
        ];
    }

    private function checksum(array $bids, array $asks, int $lastSequence): string
    {
        return hash('sha256', json_encode([
            'last_sequence' => $lastSequence,
            'bids' => $bids,
            'asks' => $asks,
        ], JSON_UNESCAPED_SLASHES));
    }
}
