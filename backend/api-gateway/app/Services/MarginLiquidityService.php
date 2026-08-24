<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginLendingPool;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarginLiquidityService
{
    public function __construct(private readonly SettlementService $settlement)
    {
    }

    public function pool(string $asset): ?MarginLendingPool
    {
        return MarginLendingPool::query()->where('asset', strtoupper($asset))->first();
    }

    public function fundPool(string $asset, string $amount, string $reference): MarginLendingPool
    {
        return DB::transaction(function () use ($amount, $asset, $reference): MarginLendingPool {
            $asset = strtoupper($asset);
            $normalized = FinancialDecimal::normalize($amount);
            $pool = MarginLendingPool::query()->where('asset', $asset)->lockForUpdate()->firstOrCreate([
                'asset' => $asset,
            ], [
                'total_liquidity' => '0',
                'available_liquidity' => '0',
                'borrowed_liquidity' => '0',
                'reserve_balance' => '0',
                'status' => 'ENABLED',
            ]);

            $this->settlement->marginPoolFunding($asset, $normalized, $reference, ['asset' => $asset]);
            $pool->total_liquidity = FinancialDecimal::add((string) $pool->total_liquidity, $normalized);
            $pool->available_liquidity = FinancialDecimal::add((string) $pool->available_liquidity, $normalized);
            $pool->status = 'ENABLED';
            $pool->save();

            return $pool->fresh();
        });
    }

    public function consumeLiquidity(string $asset, string $amount): MarginLendingPool
    {
        return DB::transaction(function () use ($amount, $asset): MarginLendingPool {
            $pool = MarginLendingPool::query()->where('asset', strtoupper($asset))->lockForUpdate()->firstOrFail();
            if ($pool->status !== 'ENABLED') {
                throw new RuntimeException('Margin lending pool is not enabled.');
            }
            if (FinancialDecimal::compare((string) $pool->available_liquidity, $amount) < 0) {
                throw new RuntimeException('Insufficient margin lending liquidity.');
            }

            $pool->available_liquidity = FinancialDecimal::sub((string) $pool->available_liquidity, $amount);
            $pool->borrowed_liquidity = FinancialDecimal::add((string) $pool->borrowed_liquidity, $amount);
            $pool->save();

            return $pool->fresh();
        });
    }

    public function restoreLiquidity(string $asset, string $principalAmount, string $reserveAmount = '0'): MarginLendingPool
    {
        return DB::transaction(function () use ($asset, $principalAmount, $reserveAmount): MarginLendingPool {
            $pool = MarginLendingPool::query()->where('asset', strtoupper($asset))->lockForUpdate()->firstOrFail();
            $pool->available_liquidity = FinancialDecimal::add((string) $pool->available_liquidity, $principalAmount);
            $pool->borrowed_liquidity = FinancialDecimal::max('0', FinancialDecimal::sub((string) $pool->borrowed_liquidity, $principalAmount));
            $pool->reserve_balance = FinancialDecimal::add((string) $pool->reserve_balance, $reserveAmount);
            $pool->save();

            return $pool->fresh();
        });
    }
}
