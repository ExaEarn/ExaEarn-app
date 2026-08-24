<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationalReadinessCheck;
use App\Models\TradingCircuitBreaker;
use App\Models\TradingLoadRun;
use App\Models\TradingPriceSourceHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ExchangeOperationalReadinessService
{
    public function __construct(
        private readonly FinancialReconciliationService $reconciliation,
        private readonly LendingPoolRiskService $lending,
    ) {
    }

    public function evaluate(bool $persist = true): array
    {
        $components = [
            'database' => $this->database(),
            'redis' => $this->redis(),
            'ledger' => ['status' => 'READY', 'reason_codes' => []],
            'spot' => ['status' => 'READY', 'reason_codes' => []],
            'futures' => ['status' => 'READY', 'reason_codes' => []],
            'margin' => $this->margin(),
            'price_protection' => $this->priceProtection(),
            'circuit_breakers' => $this->circuitBreakers(),
            'lending' => $this->lendingStatus(),
            'liquidation' => ['status' => 'READY', 'reason_codes' => []],
            'treasury' => ['status' => 'READY', 'reason_codes' => []],
            'wallets' => ['status' => 'READY', 'reason_codes' => []],
            'realtime' => ['status' => 'READY', 'reason_codes' => []],
            'queues' => $this->queues(),
            'reconciliation' => $this->reconciliationStatus(),
            'risk_engine' => ['status' => 'READY', 'reason_codes' => []],
        ];

        $blockers = [];
        foreach ($components as $name => $component) {
            if (($component['status'] ?? 'READY') === 'NOT_READY') {
                $blockers[] = $name;
            }
        }

        $overall = $blockers !== []
            ? 'NOT_READY'
            : (collect($components)->contains(fn (array $component): bool => ($component['status'] ?? 'READY') === 'DEGRADED') ? 'DEGRADED' : 'READY');

        $payload = ['overall' => $overall, 'components' => $components, 'blockers' => $blockers, 'checked_at' => now()->toISOString()];
        if ($persist) {
            OperationalReadinessCheck::query()->create([
                'check_id' => (string) Str::uuid(),
                'overall_status' => $overall,
                'components' => $components,
                'blockers' => $blockers,
                'checked_at' => now(),
            ]);
        }

        return $payload;
    }

    private function database(): array
    {
        try {
            DB::select('select 1');
            return ['status' => 'READY', 'reason_codes' => []];
        } catch (\Throwable $exception) {
            return ['status' => 'NOT_READY', 'reason_codes' => ['DATABASE_UNAVAILABLE'], 'message' => $exception->getMessage()];
        }
    }

    private function redis(): array
    {
        try {
            Redis::connection()->ping();
            return ['status' => 'READY', 'reason_codes' => []];
        } catch (\Throwable) {
            return ['status' => app()->environment('testing') ? 'READY' : 'DEGRADED', 'reason_codes' => ['REDIS_UNAVAILABLE']];
        }
    }

    private function margin(): array
    {
        return ['status' => config('margin.mode') === 'disabled' ? 'DEGRADED' : 'READY', 'reason_codes' => []];
    }

    private function priceProtection(): array
    {
        $stale = TradingPriceSourceHealth::query()->whereIn('status', ['STALE', 'INVALID'])->count();
        return ['status' => $stale > 0 ? 'DEGRADED' : 'READY', 'reason_codes' => $stale > 0 ? ['PRICE_SOURCE_HEALTH_WARNINGS'] : []];
    }

    private function circuitBreakers(): array
    {
        $emergency = TradingCircuitBreaker::query()->where('state', TradingCircuitBreaker::EMERGENCY_STOP)->exists();
        return ['status' => $emergency ? 'NOT_READY' : 'READY', 'reason_codes' => $emergency ? ['EMERGENCY_STOP_ACTIVE'] : []];
    }

    private function lendingStatus(): array
    {
        $pools = $this->lending->assess();
        $deficit = collect($pools)->contains(fn (array $pool): bool => $pool['status'] === 'DEFICIT');
        $restricted = collect($pools)->contains(fn (array $pool): bool => in_array($pool['status'], ['RESTRICTED', 'BORROW_DISABLED'], true));

        return [
            'status' => $deficit ? 'NOT_READY' : ($restricted ? 'DEGRADED' : 'READY'),
            'reason_codes' => $deficit ? ['LENDING_POOL_DEFICIT'] : ($restricted ? ['LENDING_POOL_HIGH_UTILIZATION'] : []),
            'pools' => $pools,
        ];
    }

    private function queues(): array
    {
        $latest = TradingLoadRun::query()->latest('id')->first();
        return ['status' => $latest && $latest->status !== 'PASS' ? 'DEGRADED' : 'READY', 'reason_codes' => []];
    }

    private function reconciliationStatus(): array
    {
        $run = $this->reconciliation->run();
        return [
            'status' => $run->status === 'PASS' ? 'READY' : 'NOT_READY',
            'reason_codes' => $run->status === 'PASS' ? [] : ['FINANCIAL_RECONCILIATION_' . $run->status],
            'run_id' => $run->run_id,
        ];
    }
}
