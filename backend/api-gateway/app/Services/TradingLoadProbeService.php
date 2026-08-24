<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TradingLoadRun;
use Illuminate\Support\Str;

class TradingLoadProbeService
{
    public function run(string $scope = 'risk_engine', int $iterations = 0): TradingLoadRun
    {
        $iterations = $iterations > 0 ? min($iterations, 1000) : (int) config('trading_operations.load_probe_iterations');
        $started = microtime(true);
        $latencies = [];
        $failures = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $tick = microtime(true);
            try {
                FinancialDecimal::add('1.000000000000000000', '2.000000000000000000');
            } catch (\Throwable) {
                $failures++;
            }
            $latencies[] = (microtime(true) - $tick) * 1000;
        }

        sort($latencies);
        $duration = (microtime(true) - $started) * 1000;

        return TradingLoadRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'scope' => $scope,
            'iterations' => $iterations,
            'operations' => $iterations,
            'failures' => $failures,
            'p50_ms' => $this->percentile($latencies, 0.50),
            'p95_ms' => $this->percentile($latencies, 0.95),
            'p99_ms' => $this->percentile($latencies, 0.99),
            'duration_ms' => number_format($duration, 6, '.', ''),
            'status' => $failures === 0 ? 'PASS' : 'FAIL',
            'metrics' => ['local_probe' => true],
            'started_at' => now()->subMilliseconds((int) $duration),
            'finished_at' => now(),
        ]);
    }

    private function percentile(array $values, float $percentile): string
    {
        if ($values === []) {
            return '0';
        }

        $index = min(count($values) - 1, max(0, (int) floor((count($values) - 1) * $percentile)));
        return number_format((float) $values[$index], 6, '.', '');
    }
}
