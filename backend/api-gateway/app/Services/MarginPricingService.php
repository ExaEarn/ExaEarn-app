<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class MarginPricingService
{
    public function price(string $asset): string
    {
        $asset = strtoupper($asset);
        $prices = (array) config('margin.reference_prices', []);
        $price = (string) ($prices[$asset] ?? '');

        if ($price === '' || FinancialDecimal::compare($price, '0') <= 0) {
            throw new RuntimeException("No valid margin reference price for {$asset}.");
        }

        return FinancialDecimal::normalize($price);
    }

    public function value(string $asset, string $amount): string
    {
        return FinancialDecimal::mul($amount, $this->price($asset));
    }
}
