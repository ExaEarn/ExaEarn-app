<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardRate;
use RuntimeException;

class GiftCardRateEngine
{
    /**
     * Get the current rate for a brand.
     *
     * @param string $brand Gift card brand (Amazon, iTunes, Google Play, etc.)
     * @return GiftCardRate
     */
    public function getRate(string $brand): GiftCardRate
    {
        $rate = GiftCardRate::active()
            ->byBrand($brand)
            ->first();

        if (!$rate) {
            throw new RuntimeException("No active rate found for brand: {$brand}");
        }

        return $rate;
    }

    /**
     * Calculate user payout.
     *
     * @param string $brand
     * @param string $cardValue
     * @return array ['payout' => decimal, 'rate' => decimal, 'profit' => decimal]
     */
    public function calculatePayout(string $brand, string $cardValue): array
    {
        $rate = $this->getRate($brand);

        if (bccomp((string) $cardValue, (string) $rate->min_value, 2) < 0) {
            throw new RuntimeException("Card value below minimum of {$rate->min_value}");
        }

        if (bccomp((string) $cardValue, (string) $rate->max_value, 2) > 0) {
            throw new RuntimeException("Card value exceeds maximum of {$rate->max_value}");
        }

        $payout = bcmul($cardValue, (string) $rate->rate, 2);
        $profit = bcsub($cardValue, $payout, 2);

        return [
            'payout' => $payout,
            'rate' => (string) $rate->rate,
            'profit' => $profit,
            'platform_keeps' => $profit,
        ];
    }

    /**
     * Update rate for a brand.
     *
     * @param string $brand
     * @param float $newRate
     * @param int|null $minValue
     * @param int|null $maxValue
     */
    public function updateRate(string $brand, float $newRate, ?int $minValue = null, ?int $maxValue = null): GiftCardRate
    {
        if ($newRate < 0 || $newRate > 1.0) {
            throw new RuntimeException('Rate must be between 0 and 1.0');
        }

        $rate = GiftCardRate::byBrand($brand)->first();

        if (!$rate) {
            $rate = new GiftCardRate();
            $rate->brand = $brand;
        }

        $rate->rate = $newRate;
        if ($minValue !== null) {
            $rate->min_value = $minValue;
        }
        if ($maxValue !== null) {
            $rate->max_value = $maxValue;
        }
        $rate->save();

        return $rate;
    }

    /**
     * Get all active rates.
     *
     * @return array
     */
    public function getAllRates(): array
    {
        return GiftCardRate::active()->get()->toArray();
    }
}