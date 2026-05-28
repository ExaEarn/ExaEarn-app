<?php

declare(strict_types=1);

namespace App\Services;

class SpreadEngineService
{
    public function calculate(float $volatility, float $volume): float
    {
        $base = 0.5;
        if ($volatility > 0.03) {
            $base += 0.8;
        } elseif ($volatility > 0.015) {
            $base += 0.4;
        }

        if ($volume > 500000) {
            $base -= 0.2;
        }

        $min = (float) config('market_maker.spread.min_percent', 0.2);
        $max = (float) config('market_maker.spread.max_percent', 2.5);

        return max($min, min($max, $base));
    }
}
