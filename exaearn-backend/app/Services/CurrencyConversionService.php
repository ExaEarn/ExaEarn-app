<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CurrencyRate;
use RuntimeException;

class CurrencyConversionService
{
    public function convert(string $amount, string $fromCurrency, string $toCurrency): string
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return $this->normalize($amount);
        }

        $usdAmount = $this->toUsdt($amount, $fromCurrency);

        if ($toCurrency === 'USDT') {
            return $usdAmount;
        }

        $targetRate = $this->getRate($toCurrency);

        if (bccomp($targetRate, '0', 18) <= 0) {
            throw new RuntimeException("Invalid rate for {$toCurrency}");
        }

        return bcdiv($usdAmount, $targetRate, 18);
    }

    private function toUsdt(string $amount, string $currency): string
    {
        if ($currency === 'USDT') {
            return $this->normalize($amount);
        }

        $rate = $this->getRate($currency);

        if (bccomp($rate, '0', 18) <= 0) {
            throw new RuntimeException("Invalid rate for {$currency}");
        }

        return bcmul($amount, $rate, 18);
    }

    private function getRate(string $currency): string
    {
        $rate = CurrencyRate::where('currency', $currency)->value('rate_to_usdt');

        if ($rate === null) {
            throw new RuntimeException("No currency rate configured for {$currency}");
        }

        return (string) $rate;
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', 18);
    }
}
