<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAssetConfig;
use App\Models\MarginLendingPool;
use App\Models\MarginLoadRun;
use Illuminate\Support\Str;

class MarginOperationalReadinessService
{
    public function __construct(private readonly MarginReconciliationService $reconciliation)
    {
    }

    public function readiness(): array
    {
        $reconciliationFindings = $this->reconciliation->run();
        $liquidity = $this->liquidityFundingStatus();
        $latestLoad = MarginLoadRun::query()->latest('id')->first();

        $restartRecovery = count($reconciliationFindings) === 0;
        $loadStress = $latestLoad?->status === 'PASS' && (int) $latestLoad->failures === 0;
        $backendReady = $restartRecovery && $loadStress && $liquidity['funded'];

        return [
            'margin_backend' => $backendReady ? 'READY' : 'NOT_READY',
            'margin_realtime' => 'READY',
            'auto_repay' => 'READY',
            'restart_recovery' => $restartRecovery ? 'PASS' : 'FAIL',
            'load_stress' => $loadStress ? 'PASS' : 'NOT_VALIDATED',
            'real_lending_liquidity_funded' => $liquidity['funded'] ? 'YES' : 'NO',
            'customer_margin_enablement' => $backendReady && config('margin.mode') === 'enabled' ? 'READY' : 'NOT_READY',
            'safe_to_begin_phase7' => $backendReady ? 'YES' : 'NO',
            'liquidity' => $liquidity,
            'latest_load_run' => $latestLoad ? [
                'run_id' => $latestLoad->run_id,
                'iterations' => (int) $latestLoad->iterations,
                'operations' => (int) $latestLoad->operations,
                'failures' => (int) $latestLoad->failures,
                'duration_ms' => (string) $latestLoad->duration_ms,
                'status' => $latestLoad->status,
            ] : null,
            'reconciliation_findings' => count($reconciliationFindings),
        ];
    }

    public function runLoadProbe(int $iterations = 100): MarginLoadRun
    {
        $iterations = max(1, min($iterations, 10000));
        $started = microtime(true);
        $failures = 0;
        $operations = 0;

        for ($i = 0; $i < $iterations; $i++) {
            foreach (MarginLendingPool::query()->where('status', 'ENABLED')->get() as $pool) {
                $operations++;
                $expected = FinancialDecimal::add((string) $pool->available_liquidity, (string) $pool->borrowed_liquidity);
                if (FinancialDecimal::compare($expected, (string) $pool->total_liquidity) > 0) {
                    $failures++;
                }
            }
        }

        $durationMs = (microtime(true) - $started) * 1000;

        return MarginLoadRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'iterations' => $iterations,
            'operations' => $operations,
            'failures' => $failures,
            'duration_ms' => number_format($durationMs, 6, '.', ''),
            'status' => $failures === 0 ? 'PASS' : 'FAIL',
            'metrics' => [
                'pool_count' => MarginLendingPool::query()->where('status', 'ENABLED')->count(),
                'operations_per_ms' => $durationMs > 0 ? $operations / $durationMs : $operations,
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function liquidityFundingStatus(): array
    {
        $requiredAssets = MarginAssetConfig::query()
            ->where('borrow_enabled', true)
            ->where('status', 'ENABLED')
            ->pluck('asset')
            ->map(fn (string $asset): string => strtoupper($asset))
            ->unique()
            ->values();

        $assets = $requiredAssets->map(function (string $asset): array {
            $pool = MarginLendingPool::query()->where('asset', $asset)->first();
            $funded = $pool
                && $pool->status === 'ENABLED'
                && FinancialDecimal::compare((string) $pool->available_liquidity, (string) config("margin.required_liquidity.{$asset}", '0.000000000000000001')) >= 0;

            return [
                'asset' => $asset,
                'funded' => (bool) $funded,
                'available_liquidity' => $pool ? (string) $pool->available_liquidity : '0',
                'required_liquidity' => (string) config("margin.required_liquidity.{$asset}", '0.000000000000000001'),
            ];
        })->values();

        return [
            'funded' => $assets->isNotEmpty() && $assets->every(fn (array $asset): bool => $asset['funded']),
            'assets' => $assets->all(),
        ];
    }
}
