<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesMarkPriceSnapshot;
use App\Models\FuturesMarket;
use RuntimeException;

class FuturesMarkPriceService
{
    public function calculate(FuturesMarket $market, string $indexPrice, string $perpetualPrice, string $fundingBasis = '0'): array
    {
        if (FinancialDecimal::compare($indexPrice, '0') <= 0) {
            throw new RuntimeException('Index price must be positive.');
        }

        $premium = FinancialDecimal::div(FinancialDecimal::sub($perpetualPrice, $indexPrice), $indexPrice, 10);
        $cap = (string) config('futures.mark.max_premium_rate', '0.01');
        if (FinancialDecimal::compare($premium, $cap, 10) > 0) {
            $premium = $cap;
        }
        if (FinancialDecimal::compare($premium, FinancialDecimal::sub('0', $cap), 10) < 0) {
            $premium = FinancialDecimal::sub('0', $cap, 10);
        }

        $mark = FinancialDecimal::mul($indexPrice, FinancialDecimal::add('1', FinancialDecimal::add($premium, $fundingBasis, 10), 10));

        FuturesMarkPriceSnapshot::query()->create([
            'futures_market_id' => $market->id,
            'symbol' => $market->symbol,
            'index_price' => $indexPrice,
            'mark_price' => $mark,
            'premium_rate' => $premium,
            'metadata' => ['perpetual_price' => $perpetualPrice, 'funding_basis' => $fundingBasis],
            'calculated_at' => now(),
        ]);

        $market->mark_price = $mark;
        $market->funding_rate = $this->fundingRateFromPremium($premium);
        $market->save();

        return ['mark_price' => $mark, 'premium_rate' => $premium, 'funding_rate' => (string) $market->funding_rate];
    }

    public function fundingRateFromPremium(string $premiumRate): string
    {
        $interest = (string) config('futures.funding.interest_rate', '0.0001');
        $rate = FinancialDecimal::add($premiumRate, $interest, 10);
        $max = (string) config('futures.funding.max_rate', '0.0075');
        $min = (string) config('futures.funding.min_rate', '-0.0075');

        if (FinancialDecimal::compare($rate, $max, 10) > 0) {
            return $max;
        }
        if (FinancialDecimal::compare($rate, $min, 10) < 0) {
            return $min;
        }

        return $rate;
    }
}
