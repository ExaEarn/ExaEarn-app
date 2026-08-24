<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TradingCircuitBreaker;
use App\Models\TradingMarketState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CircuitBreakerService
{
    private const BLOCKING_NEW_ORDER_STATES = [
        TradingCircuitBreaker::CANCEL_ONLY,
        TradingCircuitBreaker::REDUCE_ONLY,
        TradingCircuitBreaker::PAUSED,
        TradingCircuitBreaker::EMERGENCY_STOP,
    ];

    public function __construct(private readonly TradingOperationalAuditService $audit)
    {
    }

    public function state(string $scope, string $scopeKey = '*'): string
    {
        return (string) (TradingCircuitBreaker::query()
            ->where('scope', strtoupper($scope))
            ->where('scope_key', strtoupper($scopeKey))
            ->value('state') ?? TradingCircuitBreaker::NORMAL);
    }

    public function assertAllowsNewRisk(string $product, ?string $marketSymbol = null, bool $reduceOnly = false): array
    {
        foreach ([['GLOBAL', '*'], ['PRODUCT', strtoupper($product)], ['MARKET', strtoupper((string) $marketSymbol)]] as [$scope, $key]) {
            if ($scope === 'MARKET' && $key === '') {
                continue;
            }

            $state = $this->state($scope, $key);
            if ($state === TradingCircuitBreaker::NORMAL || $state === TradingCircuitBreaker::WARNING) {
                continue;
            }

            if ($state === TradingCircuitBreaker::REDUCE_ONLY && $reduceOnly) {
                continue;
            }

            return [
                'allowed' => false,
                'state' => $state,
                'reason_code' => match ($state) {
                    TradingCircuitBreaker::CANCEL_ONLY => 'MARKET_CANCEL_ONLY',
                    TradingCircuitBreaker::PAUSED => 'MARKET_PAUSED',
                    TradingCircuitBreaker::REDUCE_ONLY => 'REDUCE_ONLY',
                    default => 'SYSTEM_RISK_LIMIT',
                },
                'scope' => $scope,
                'scope_key' => $key,
            ];
        }

        return ['allowed' => true, 'state' => TradingCircuitBreaker::NORMAL];
    }

    public function transition(
        string $scope,
        string $scopeKey,
        string $state,
        string $reason,
        ?int $adminId = null,
        ?string $reasonCode = null,
        array $metadata = [],
    ): TradingCircuitBreaker {
        return DB::transaction(function () use ($adminId, $metadata, $reason, $reasonCode, $scope, $scopeKey, $state): TradingCircuitBreaker {
            $scope = strtoupper($scope);
            $scopeKey = strtoupper($scopeKey);
            $state = strtoupper($state);

            $breaker = TradingCircuitBreaker::query()
                ->where('scope', $scope)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();
            $before = $breaker?->toArray();

            $breaker = TradingCircuitBreaker::query()->updateOrCreate([
                'scope' => $scope,
                'scope_key' => $scopeKey,
            ], [
                'breaker_id' => $breaker?->breaker_id ?? (string) Str::uuid(),
                'state' => $state,
                'reason_code' => $reasonCode,
                'reason' => $reason,
                'changed_by_admin_id' => $adminId,
                'activated_at' => $state === TradingCircuitBreaker::NORMAL ? null : now(),
                'cleared_at' => $state === TradingCircuitBreaker::NORMAL ? now() : null,
                'metadata' => $metadata,
            ]);

            if ($scope === 'MARKET') {
                TradingMarketState::query()->updateOrCreate([
                    'market_symbol' => $scopeKey,
                    'product' => (string) ($metadata['product'] ?? 'spot'),
                ], [
                    'state' => $state,
                    'reason_code' => $reasonCode,
                    'reason' => $reason,
                    'changed_by_admin_id' => $adminId,
                    'changed_at' => now(),
                    'metadata' => $metadata,
                ]);
            }

            $this->audit->record('circuit_breaker.transition', $scope, $scopeKey, $before, $breaker->toArray(), $reason, $adminId);

            return $breaker->fresh() ?? $breaker;
        });
    }
}
