<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardRate;
use RuntimeException;

/**
 * Gift Card Pricing Engine
 *
 * Calculates buy and sell prices with markup/discount rates.
 */
class GiftCardPricingEngine
{
    /**
     * Get sell rate for a brand.
     * Users receive this rate when selling cards to the platform.
     *
     * @return array{rate: float, min_value: int, max_value: int}
     */
    public function getSellRate(string $brand, string $currency = 'USD'): array
    {
        $rate = $this->resolveRate($brand, $currency);

        return [
            'rate' => (float) $rate->rate,
            'min_value' => (int) $rate->min_value,
            'max_value' => (int) $rate->max_value,
        ];
    }

    /**
     * Get buy price for a gift card.
     * Platform calculates the cost to the user based on card value and markup.
     *
     * @return array{card_value: float, buy_price: float, markup_rate: float}
     */
    public function getBuyPrice(string $brand, float $cardValue, string $currency = 'USD'): array
    {
        $rate = $this->resolveRate($brand, $currency);

        // Buy price is typically markup on the sell rate
        // Example: If sell rate is 0.85 (85%), buy rate might be 1.15 (users pay 115% of card value)
        $markupRate = $this->getMarkupRate($brand);
        $buyPrice = $cardValue * $markupRate;

        // Validate card value is within acceptable range
        if ($cardValue < $rate->min_value || $cardValue > $rate->max_value) {
            throw new RuntimeException(
                "Card value {$cardValue} is outside allowed range ({$rate->min_value} - {$rate->max_value})"
            );
        }

        return [
            'card_value' => $cardValue,
            'buy_price' => round($buyPrice, 2),
            'markup_rate' => $markupRate,
        ];
    }

    /**
     * Calculate total purchase amount for multiple cards.
     *
     * @return array{unit_price: float, quantity: int, subtotal: float, platform_fee: float, total: float}
     */
    public function calculateTotalPrice(string $brand, float $cardValue, int $quantity, string $currency = 'USD'): array
    {
        $pricing = $this->getBuyPrice($brand, $cardValue, $currency);
        $unitPrice = $pricing['buy_price'];
        $subtotal = $unitPrice * $quantity;

        // Calculate platform fee (e.g., 2% of subtotal)
        $platformFeePercent = (float) config('giftcard.platform_fee_percent', 0.02);
        $platformFee = round($subtotal * $platformFeePercent, 2);

        $total = $subtotal + $platformFee;

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'platform_fee' => $platformFee,
            'total' => $total,
        ];
    }

    /**
     * Get markup rate for buying gift cards.
     * Platform profit = markup_rate * card_value - (sell_rate * card_value).
     */
    private function getMarkupRate(string $brand): float
    {
        // Default markup: 110% (users pay 110% of card value)
        // This gives the platform 10% margin after accounting for acquisition costs
        $brandMarkups = config('giftcard.brand_markups', []);

        return $brandMarkups[strtolower($brand)] ?? (float) config('giftcard.default_markup_rate', 1.10);
    }

    private function resolveRate(string $brand, string $currency): GiftCardRate
    {
        $brand = strtolower($brand);
        $currency = strtoupper($currency);

        $rate = GiftCardRate::query()
            ->where('brand', $brand)
            ->where('currency', $currency)
            ->active()
            ->first();

        if ($rate) {
            return $rate;
        }

        $fallbackCurrency = (string) config('giftcard.default_rate_currency', 'USD');
        $fallback = GiftCardRate::query()
            ->where('brand', $brand)
            ->where('currency', strtoupper($fallbackCurrency))
            ->active()
            ->first();

        if ($fallback) {
            return $fallback;
        }

        return GiftCardRate::query()
            ->where('brand', $brand)
            ->active()
            ->firstOrFail();
    }

    /**
     * Calculate platform profit from a transaction.
     */
    public function calculateProfit(string $brand, float $cardValue, float $sellPriceReceived): float
    {
        $buyPricing = $this->getBuyPrice($brand, $cardValue);
        $buyPrice = $buyPricing['buy_price'];

        // Profit = (buy_price) - (sell_price_received) - (processing_costs)
        $processingCost = $cardValue * (float) config('giftcard.processing_cost_percent', 0.01);

        return $buyPrice - $sellPriceReceived - $processingCost;
    }
}
