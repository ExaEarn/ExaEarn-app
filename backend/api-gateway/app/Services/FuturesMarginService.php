<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesMarket;

class FuturesMarginService
{
    public function __construct(private readonly FuturesInstrumentService $instruments)
    {
    }

    public function notional(string $price, string $quantity): string
    {
        return FinancialDecimal::mul($price, $quantity);
    }

    public function initialMargin(FuturesMarket $market, string $notional, int $leverage, string $feeBuffer = '0'): string
    {
        $tier = $this->instruments->tierForNotional($market, $notional);
        $maxLeverage = min((int) $market->max_leverage, (int) ($tier['max_leverage'] ?? $market->max_leverage));
        if ($leverage > $maxLeverage) {
            throw new \RuntimeException('Leverage exceeds the active risk tier.');
        }

        return FinancialDecimal::add(FinancialDecimal::div($notional, (string) $leverage), $feeBuffer);
    }

    public function maintenanceMargin(FuturesMarket $market, string $notional): string
    {
        $tier = $this->instruments->tierForNotional($market, $notional);
        $rate = (string) ($tier['maintenance_margin_rate'] ?? $market->maintenance_margin_rate ?? '0.005');
        $amount = (string) ($tier['maintenance_amount'] ?? '0');

        return FinancialDecimal::add(FinancialDecimal::mul($notional, $rate), $amount);
    }

    public function unrealizedPnl(string $side, string $entryPrice, string $markPrice, string $quantity): string
    {
        $diff = strtolower($side) === 'long'
            ? FinancialDecimal::sub($markPrice, $entryPrice)
            : FinancialDecimal::sub($entryPrice, $markPrice);

        return FinancialDecimal::mul($diff, $quantity);
    }

    public function liquidationPrice(string $side, string $entryPrice, string $quantity, string $margin, string $maintenanceMargin): string
    {
        if (FinancialDecimal::compare($quantity, '0') <= 0) {
            return '0.00000000';
        }

        $buffer = FinancialDecimal::div(FinancialDecimal::sub($margin, $maintenanceMargin), $quantity);

        return strtolower($side) === 'long'
            ? FinancialDecimal::sub($entryPrice, $buffer)
            : FinancialDecimal::add($entryPrice, $buffer);
    }

    public function bankruptcyPrice(string $side, string $entryPrice, string $quantity, string $margin): string
    {
        if (FinancialDecimal::compare($quantity, '0') <= 0) {
            return '0.00000000';
        }

        $buffer = FinancialDecimal::div($margin, $quantity);

        return strtolower($side) === 'long'
            ? FinancialDecimal::sub($entryPrice, $buffer)
            : FinancialDecimal::add($entryPrice, $buffer);
    }
}
