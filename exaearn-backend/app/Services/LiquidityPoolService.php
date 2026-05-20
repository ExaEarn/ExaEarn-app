<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiquidityPool;

class LiquidityPoolService
{
    public function addLiquidity(string $symbol, string $base, string $quote): LiquidityPool
    {
        $pool = LiquidityPool::query()->firstOrCreate(['symbol' => strtoupper($symbol)]);
        $pool->base_asset_balance = $this->add((string) $pool->base_asset_balance, $base);
        $pool->quote_asset_balance = $this->add((string) $pool->quote_asset_balance, $quote);
        $pool->save();

        return $pool;
    }

    public function removeLiquidity(string $symbol, string $base, string $quote): LiquidityPool
    {
        $pool = LiquidityPool::query()->where('symbol', strtoupper($symbol))->firstOrFail();
        $pool->base_asset_balance = $this->sub((string) $pool->base_asset_balance, $base);
        $pool->quote_asset_balance = $this->sub((string) $pool->quote_asset_balance, $quote);
        $pool->save();

        return $pool;
    }

    public function rebalancePool(string $symbol, string $targetBaseRatio = '0.5'): LiquidityPool
    {
        $pool = LiquidityPool::query()->where('symbol', strtoupper($symbol))->firstOrFail();
        // Placeholder rebalance marker - in production, execute treasury transfers.
        $pool->save();

        return $pool;
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 8) : (string) ((float) $a + (float) $b);
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 8) : (string) ((float) $a - (float) $b);
    }
}
