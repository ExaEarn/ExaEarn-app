<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAssetConfig;
use App\Models\MarginLendingPool;

class MarginInterestRateService
{
    public function annualRate(MarginAssetConfig $config, ?MarginLendingPool $pool = null): string
    {
        $utilization = $pool ? $this->utilization($pool) : '0';
        $base = (string) $config->base_rate;
        $slope1 = (string) $config->slope_1;
        $slope2 = (string) $config->slope_2;
        $optimal = (string) $config->optimal_utilization;
        $max = (string) $config->max_rate;

        if (FinancialDecimal::compare($optimal, '0') <= 0) {
            return FinancialDecimal::min($max, FinancialDecimal::add($base, $slope1));
        }

        if (FinancialDecimal::compare($utilization, $optimal) <= 0) {
            $ratio = FinancialDecimal::div($utilization, $optimal);
            $rate = FinancialDecimal::add($base, FinancialDecimal::mul($slope1, $ratio));

            return FinancialDecimal::min($max, $rate);
        }

        $excess = FinancialDecimal::sub($utilization, $optimal);
        $denominator = FinancialDecimal::sub('1', $optimal);
        $excessRatio = FinancialDecimal::compare($denominator, '0') > 0 ? FinancialDecimal::div($excess, $denominator) : '1';
        $rate = FinancialDecimal::add(FinancialDecimal::add($base, $slope1), FinancialDecimal::mul($slope2, $excessRatio));

        return FinancialDecimal::min($max, $rate);
    }

    public function utilization(MarginLendingPool $pool): string
    {
        if (FinancialDecimal::compare((string) $pool->total_liquidity, '0') <= 0) {
            return '0.000000000000000000';
        }

        return FinancialDecimal::div((string) $pool->borrowed_liquidity, (string) $pool->total_liquidity);
    }
}
