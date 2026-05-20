<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AdaptiveMarketMakerService
{
    public function __construct(private readonly SmartLiquidityService $smartLiquidity)
    {
    }

    public function adjust(string $symbol, array $aiOutput): array
    {
        return $this->smartLiquidity->apply($symbol, $aiOutput);
    }

    public function current(string $symbol): array
    {
        return (array) Cache::get('ai_intel:mm:' . strtoupper($symbol), [
            'optimal_spread' => 0.002,
            'order_size' => 200,
            'risk_level' => 'medium',
            'trend' => 'adaptive',
        ]);
    }
}
