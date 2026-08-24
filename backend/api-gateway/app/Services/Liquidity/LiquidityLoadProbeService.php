<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\TradingLoadRun;
use Illuminate\Support\Str;

class LiquidityLoadProbeService
{
    public function run(int $iterations = 100): TradingLoadRun
    {
        $iterations = max(1, min($iterations, 5000));
        $started = microtime(true);
        $latencies = [];
        $failures = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $tick = microtime(true);
            try {
                app(LiquidityOperationalReadinessService::class)->check();
            } catch (\Throwable) {
                $failures++;
            }
            $latencies[] = (microtime(true) - $tick) * 1000;
        }

        sort($latencies);
        $percentile = fn (float $p): string => (string) round($latencies[(int) min(count($latencies) - 1, floor((count($latencies) - 1) * $p))], 6);

        return TradingLoadRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'scope' => 'phase8_liquidity',
            'iterations' => $iterations,
            'operations' => $iterations,
            'failures' => $failures,
            'p50_ms' => $percentile(0.50),
            'p95_ms' => $percentile(0.95),
            'p99_ms' => $percentile(0.99),
            'duration_ms' => (string) round((microtime(true) - $started) * 1000, 6),
            'status' => $failures === 0 ? 'PASS' : 'FAIL',
            'metrics' => ['mode' => 'local_control_plane_probe'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
