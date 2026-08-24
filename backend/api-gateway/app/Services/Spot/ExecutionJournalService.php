<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\Order;
use App\Models\SpotExecutionEvent;
use Illuminate\Support\Str;

class ExecutionJournalService
{
    public function record(Market $market, int $sequence, string $eventType, array $payload, ?Order $order = null, ?string $executionId = null): SpotExecutionEvent
    {
        return SpotExecutionEvent::query()->firstOrCreate([
            'market_id' => $market->id,
            'sequence' => $sequence,
            'event_type' => $eventType,
            'order_id' => $order?->id,
        ], [
            'event_id' => (string) Str::uuid(),
            'market_symbol' => $market->symbol,
            'execution_id' => $executionId,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    public function eventsAfter(Market $market, int $sequence): array
    {
        return SpotExecutionEvent::query()
            ->where('market_id', $market->id)
            ->where('sequence', '>', $sequence)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(fn (SpotExecutionEvent $event): array => $event->toArray())
            ->all();
    }
}
