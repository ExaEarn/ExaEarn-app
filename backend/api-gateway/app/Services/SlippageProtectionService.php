<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class SlippageProtectionService
{
    public function assertWithin(string $side, float $expectedPrice, float $actualPrice, ?float $maxSlippagePercent = null): float
    {
        $limit = $maxSlippagePercent ?? (float) config('sor.default_max_slippage_percent', 0.5);
        if ($expectedPrice <= 0 || $actualPrice <= 0) {
            throw new RuntimeException('Invalid pricing for slippage validation.');
        }

        $slippage = $side === 'buy'
            ? (($actualPrice - $expectedPrice) / $expectedPrice) * 100
            : (($expectedPrice - $actualPrice) / $expectedPrice) * 100;

        if ($slippage > $limit) {
            throw new RuntimeException('Slippage threshold exceeded.');
        }

        return $slippage;
    }
}
