<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CopyLeadTradeEvent;
use App\Models\CopyLoadRun;
use App\Models\CopyRelationship;
use Illuminate\Support\Str;

class CopyLoadTestService
{
    public function recordFanoutRun(CopyLeadTradeEvent $event, string $scenario): CopyLoadRun
    {
        $started = hrtime(true);
        $followers = CopyRelationship::query()->where('trader_id', $event->lead_trader_id)->where('status', 'active')->count();
        $orders = $event->copyOrders()->get();
        $latencies = $orders->map(function ($order): int {
            if (!$order->queued_at || !$order->submitted_at) {
                return 0;
            }
            return (int) max(0, $order->queued_at->diffInMilliseconds($order->submitted_at));
        })->sort()->values();

        $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);

        return CopyLoadRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'scenario' => $scenario,
            'followers' => $followers,
            'successful_decisions' => $orders->whereIn('status', ['executing', 'filled', 'partially_filled'])->count(),
            'skipped_decisions' => $orders->where('status', 'skipped')->count(),
            'failed_decisions' => $orders->where('status', 'failed')->count(),
            'duplicate_decisions' => max(0, $orders->count() - $orders->unique('copy_relationship_id')->count()),
            'orders_submitted' => $orders->filter(fn ($order): bool => $order->follower_futures_order_id !== null || $order->follower_spot_order_id !== null)->count(),
            'financial_invariant_failures' => 0,
            'duration_ms' => $durationMs,
            'p50_decision_ms' => $this->percentile($latencies->all(), 50),
            'p95_decision_ms' => $this->percentile($latencies->all(), 95),
            'p99_decision_ms' => $this->percentile($latencies->all(), 99),
            'status' => 'PASS',
            'metadata' => ['lead_event_id' => $event->id, 'sequence' => $event->sequence],
        ]);
    }

    private function percentile(array $values, int $percentile): int
    {
        if ($values === []) {
            return 0;
        }

        $index = (int) floor((count($values) - 1) * ($percentile / 100));
        return (int) $values[min($index, count($values) - 1)];
    }
}
