<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesMarket;
use RuntimeException;

class FuturesRiskEngineService
{
    private const SCALE = 8;

    public function validateOrderRisk(
        int $userId,
        FuturesMarket $market,
        string $side,
        int $leverage,
        string $notional,
        string $marginRequired,
    ): void {
        if (!in_array(strtolower($side), ['long', 'short'], true)) {
            throw new RuntimeException('Invalid futures side.');
        }

        if ($leverage < (int) $market->min_leverage || $leverage > (int) $market->max_leverage) {
            throw new RuntimeException('Leverage out of allowed range.');
        }

        if ($this->compare($notional, '0') <= 0) {
            throw new RuntimeException('Order notional must be greater than zero.');
        }

        if ($this->compare($marginRequired, '0') <= 0) {
            throw new RuntimeException('Margin requirement must be greater than zero.');
        }

        $maintenanceRate = (string) ($market->maintenance_margin_rate ?? '0.005');
        $maintenanceMargin = $this->mul($notional, $maintenanceRate);

        if ($this->compare($marginRequired, $maintenanceMargin) <= 0) {
            throw new RuntimeException('Initial margin must exceed maintenance margin.');
        }
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, self::SCALE) : number_format((float) $a * (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float) $a <=> (float) $b);
    }
}
