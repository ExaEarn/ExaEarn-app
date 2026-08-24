<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginLendingPool;

class LendingPoolRiskService
{
    public function assess(?string $asset = null): array
    {
        return MarginLendingPool::query()
            ->when($asset, fn ($query) => $query->where('asset', strtoupper($asset)))
            ->get()
            ->map(fn (MarginLendingPool $pool): array => $this->pool($pool))
            ->all();
    }

    public function pool(MarginLendingPool $pool): array
    {
        $total = (string) $pool->total_liquidity;
        $borrowed = (string) $pool->borrowed_liquidity;
        $utilizationBps = FinancialDecimal::compare($total, '0') > 0
            ? FinancialDecimal::mul(FinancialDecimal::div($borrowed, $total), '10000')
            : '0';

        $threshold = (string) config('trading_operations.lending_pool_high_utilization_bps');
        $state = 'HEALTHY';
        if (FinancialDecimal::compare((string) $pool->available_liquidity, '0') < 0) {
            $state = 'DEFICIT';
        } elseif (FinancialDecimal::compare($utilizationBps, '10000') >= 0) {
            $state = 'BORROW_DISABLED';
        } elseif (FinancialDecimal::compare($utilizationBps, $threshold) >= 0) {
            $state = 'RESTRICTED';
        }

        return [
            'asset' => $pool->asset,
            'status' => $state,
            'total_liquidity' => (string) $pool->total_liquidity,
            'available_liquidity' => (string) $pool->available_liquidity,
            'borrowed_liquidity' => (string) $pool->borrowed_liquidity,
            'utilization_bps' => $utilizationBps,
        ];
    }
}
