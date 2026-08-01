<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;

class FuturesPositionService
{
    private const SCALE = 8;

    public function refreshUnrealizedPnl(FuturesPosition $position, string $markPrice): FuturesPosition
    {
        $entry = (string) $position->entry_price;
        $quantity = (string) $position->quantity;
        $margin = (string) $position->margin;
        $maintenanceRate = $this->safeRate((string) $position->maintenance_margin, $markPrice, $quantity);

        $difference = $position->side === 'long'
            ? $this->sub($markPrice, $entry)
            : $this->sub($entry, $markPrice);

        $unrealized = $this->mul($difference, $quantity);
        $notional = $this->mul($markPrice, $quantity);
        $maintenanceMargin = $maintenanceRate !== null ? $this->mul($notional, $maintenanceRate) : (string) $position->maintenance_margin;
        $liquidation = $this->compare($quantity, '0') > 0
            ? ($position->side === 'long'
                ? $this->sub($entry, $this->div($margin, $quantity))
                : $this->add($entry, $this->div($margin, $quantity)))
            : (string) $position->liquidation_price;

        $position->mark_price = $markPrice;
        $position->unrealized_pnl = $unrealized;
        $position->maintenance_margin = $maintenanceMargin;
        $position->liquidation_price = $liquidation;
        $position->save();

        return $position;
    }

    private function safeRate(string $maintenanceMargin, string $markPrice, string $quantity): ?string
    {
        $notional = $this->mul($markPrice, $quantity);
        if ($this->compare($notional, '0') <= 0) {
            return null;
        }

        return $this->div($maintenanceMargin, $notional);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, self::SCALE) : number_format((float) $a * (float) $b, self::SCALE, '.', '');
    }

    private function div(string $a, string $b): string
    {
        if ($this->compare($b, '0') === 0) {
            return '0.00000000';
        }

        return function_exists('bcdiv') ? bcdiv($a, $b, self::SCALE) : number_format((float) $a / (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float) $a <=> (float) $b);
    }
}
