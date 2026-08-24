<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Services\FinancialDecimal;
use RuntimeException;

class InstrumentService
{
    public function normalizeSymbol(string $symbol): string
    {
        $clean = strtoupper(trim($symbol));
        if (str_contains($clean, '/')) {
            return $clean;
        }
        if (str_contains($clean, '-')) {
            [$base, $quote] = array_pad(explode('-', $clean, 2), 2, 'USDT');
            return trim($base) . '/' . trim($quote);
        }

        foreach (['USDT', 'USDC', 'BTC', 'ETH', 'NGN', 'USD'] as $quote) {
            if (str_ends_with($clean, $quote) && strlen($clean) > strlen($quote)) {
                return substr($clean, 0, -strlen($quote)) . '/' . $quote;
            }
        }

        return $clean;
    }

    public function market(string $symbol): Market
    {
        $normalized = $this->normalizeSymbol($symbol);
        $market = Market::query()->where('symbol', $normalized)->first();
        if (!$market) {
            throw new RuntimeException('Market not found.');
        }

        return $market;
    }

    public function assertTradable(Market $market): void
    {
        if ((string) $market->status !== 'active') {
            throw new RuntimeException('Market is not active.');
        }

        if (($market->trading_status ?? 'trading') !== 'trading') {
            throw new RuntimeException('Market is currently halted.');
        }
    }

    public function assertQuantity(Market $market, string $quantity): string
    {
        $quantity = FinancialDecimal::normalize($quantity);
        if (FinancialDecimal::compare($quantity, '0') <= 0) {
            throw new RuntimeException('Order quantity must be greater than zero.');
        }

        $min = FinancialDecimal::normalize((string) ($market->min_order_size ?: '0'));
        if (FinancialDecimal::compare($min, '0') > 0 && FinancialDecimal::compare($quantity, $min) < 0) {
            throw new RuntimeException('Order amount below market minimum.');
        }

        $max = FinancialDecimal::normalize((string) ($market->max_order_size ?: '0'));
        if (FinancialDecimal::compare($max, '0') > 0 && FinancialDecimal::compare($quantity, $max) > 0) {
            throw new RuntimeException('Order amount exceeds market maximum.');
        }

        $this->assertMultiple($quantity, $this->quantityStep($market), 'Order quantity does not match market lot size.');

        return $quantity;
    }

    public function assertPrice(Market $market, ?string $price, string $orderType): string
    {
        if ($orderType === 'market') {
            return '0.000000000000000000';
        }

        if ($price === null || FinancialDecimal::compare((string) $price, '0') <= 0) {
            throw new RuntimeException('Price is required.');
        }

        $normalized = FinancialDecimal::normalize($price);
        $this->assertMultiple($normalized, $this->tickSize($market), 'Order price does not match market tick size.');

        return $normalized;
    }

    public function assertNotional(Market $market, string $quantity, string $price): void
    {
        if (FinancialDecimal::compare($price, '0') <= 0) {
            return;
        }

        $notional = FinancialDecimal::mul($quantity, $price);
        $min = FinancialDecimal::normalize((string) ($market->min_notional ?: '0'));
        $max = FinancialDecimal::normalize((string) ($market->max_notional ?: '0'));

        if (FinancialDecimal::compare($min, '0') > 0 && FinancialDecimal::compare($notional, $min) < 0) {
            throw new RuntimeException('Order notional is below market minimum.');
        }

        if (FinancialDecimal::compare($max, '0') > 0 && FinancialDecimal::compare($notional, $max) > 0) {
            throw new RuntimeException('Order notional exceeds market maximum.');
        }
    }

    public function tickSize(Market $market): string
    {
        return FinancialDecimal::normalize((string) ($market->tick_size ?: $market->price_precision ?: '0.00000001'));
    }

    public function quantityStep(Market $market): string
    {
        return FinancialDecimal::normalize((string) ($market->quantity_step ?: '0.00000001'));
    }

    public function quoteAmount(string $quantity, string $price): string
    {
        return FinancialDecimal::mul($quantity, $price, 18);
    }

    public function toTicks(string $value, string $step): string
    {
        $ticks = FinancialDecimal::div($value, $step, 18);
        if (!preg_match('/^-?\d+(\.0+)?$/', $ticks)) {
            throw new RuntimeException('Decimal value is not an exact increment.');
        }

        return explode('.', $ticks, 2)[0];
    }

    private function assertMultiple(string $value, string $step, string $message): void
    {
        if (FinancialDecimal::compare($step, '0') <= 0) {
            return;
        }

        try {
            $this->toTicks($value, $step);
        } catch (RuntimeException) {
            throw new RuntimeException($message);
        }
    }
}
