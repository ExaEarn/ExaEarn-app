<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\SpotMarketDataEvent;
use App\Services\RealtimeStreamService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SpotRealtimeSequenceService
{
    public function record(Market $market, int $sequence, string $eventType, array $payload): SpotMarketDataEvent
    {
        $event = SpotMarketDataEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'sequence' => $sequence,
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        $this->publish($event);

        return $event;
    }

    public function snapshot(Market $market): array
    {
        $snapshot = app(OrderBookSnapshotService::class)->latest($market);

        return [
            'market' => $market->symbol,
            'last_sequence' => $snapshot?->last_sequence ?? 0,
            'bids' => $snapshot?->bids ?? [],
            'asks' => $snapshot?->asks ?? [],
            'timestamp' => now()->toISOString(),
        ];
    }

    public function deltasAfter(Market $market, int $sequence, int $limit = 500): array
    {
        $events = SpotMarketDataEvent::query()
            ->where('market_id', $market->id)
            ->where('sequence', '>', $sequence)
            ->orderBy('sequence')
            ->limit($limit)
            ->get();

        $expected = $sequence + 1;
        $lastSeen = null;
        foreach ($events as $event) {
            $current = (int) $event->sequence;
            if ($lastSeen !== null && $current === $lastSeen) {
                continue;
            }

            if ($current !== $expected) {
                throw new RuntimeException('Market-data sequence gap detected; resync required.');
            }

            $lastSeen = $current;
            $expected = $current + 1;
        }

        return $events->map(fn (SpotMarketDataEvent $event): array => [
            'market' => $event->market_symbol,
            'sequence' => (int) $event->sequence,
            'event_type' => $event->event_type,
            'timestamp' => $event->occurred_at?->toISOString(),
            'payload' => $event->payload,
        ])->all();
    }

    private function publish(SpotMarketDataEvent $event): void
    {
        $streamType = match ($event->event_type) {
            'TRADE' => 'trade',
            'BOOK_DELTA', 'BEST_BID_ASK' => 'book',
            'CANDLE', 'KLINE' => 'kline',
            default => 'ticker',
        };

        try {
            app(RealtimeStreamService::class)->publishPayload(
                (string) config('streaming.market_channel', 'exaearn.market.stream'),
                [
                    'op' => 'event',
                    'topic' => $streamType === 'kline'
                        ? 'market.' . str_replace('/', '', $event->market_symbol) . '.kline.' . (string) data_get($event->payload, 'interval', '1m')
                        : 'market.' . str_replace('/', '', $event->market_symbol) . '.' . $streamType,
                    'type' => $streamType,
                    'pair' => $event->market_symbol,
                    'symbol' => str_replace('/', '', $event->market_symbol),
                    'sequence' => (int) $event->sequence,
                    'event_type' => $event->event_type,
                    'data' => $event->payload,
                    'timestamp' => $event->occurred_at?->toISOString(),
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed to publish Spot market-data event', [
                'event_id' => $event->event_id,
                'market' => $event->market_symbol,
                'sequence' => $event->sequence,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
