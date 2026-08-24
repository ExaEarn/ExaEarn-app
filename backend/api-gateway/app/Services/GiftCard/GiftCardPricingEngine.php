<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardRate;
use App\Models\PricingRule;
use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gift Card Pricing Engine
 *
 * Calculates buy and sell prices with markup/discount rates.
 */
class GiftCardPricingEngine
{
    public function __construct(private readonly PricingPolicyEngine $pricing)
    {
    }

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
     * @return array{card_value: string, buy_price: string, markup_rate: string, pricing_snapshot: array}
     */
    public function getBuyPrice(string $brand, int|float|string $cardValue, string $currency = 'USD', array $context = []): array
    {
        $rate = $this->resolveRate($brand, $currency);
        $cardValue = FinancialDecimal::normalize((string) $cardValue, 8);

        if (FinancialDecimal::compare($cardValue, (string) $rate->min_value, 8) < 0 || FinancialDecimal::compare($cardValue, (string) $rate->max_value, 8) > 0) {
            throw new RuntimeException(
                "Card value {$cardValue} is outside allowed range ({$rate->min_value} - {$rate->max_value})"
            );
        }

        $decision = $this->preview('BUY_MARKUP', $brand, $cardValue, $currency, $context);
        $buyPrice = FinancialDecimal::add($cardValue, (string) $decision['fee_amount'], 8);
        $markupRate = FinancialDecimal::add('1', FinancialDecimal::div((string) $decision['rate_bps'], '10000', 8), 8);

        return [
            'card_value' => $cardValue,
            'buy_price' => FinancialDecimal::normalize($buyPrice, 8),
            'markup_rate' => $markupRate,
            'pricing_snapshot' => $this->snapshot($decision, [
                'brand' => strtolower($brand),
                'provider' => $context['provider'] ?? strtolower($brand),
                'denomination' => $cardValue,
                'provider_rate_id' => $rate->id,
                'provider_rate' => (string) $rate->rate,
            ]),
        ];
    }

    /**
     * Calculate total purchase amount for multiple cards.
     *
     * @return array{unit_price: string, quantity: int, subtotal: string, platform_fee: string, total: string, pricing_snapshot: array}
     */
    public function calculateTotalPrice(string $brand, int|float|string $cardValue, int $quantity, string $currency = 'USD', array $context = []): array
    {
        $pricing = $this->getBuyPrice($brand, $cardValue, $currency, $context);
        $unitPrice = $pricing['buy_price'];
        $subtotal = FinancialDecimal::mul($unitPrice, (string) $quantity, 8);
        $platformDecision = $this->preview('PLATFORM_FEE', $brand, $subtotal, $currency, $context);
        $platformFee = FinancialDecimal::normalize((string) $platformDecision['fee_amount'], 8);

        $total = FinancialDecimal::add($subtotal, $platformFee, 8);

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'platform_fee' => $platformFee,
            'total' => $total,
            'pricing_snapshot' => [
                'markup' => $pricing['pricing_snapshot'],
                'platform_fee' => $this->snapshot($platformDecision, [
                    'brand' => strtolower($brand),
                    'provider' => $context['provider'] ?? strtolower($brand),
                    'denomination' => FinancialDecimal::normalize((string) $cardValue, 8),
                ]),
            ],
        ];
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
    public function calculateProfit(string $brand, int|float|string $cardValue, int|float|string $sellPriceReceived): string
    {
        $buyPricing = $this->getBuyPrice($brand, $cardValue);
        $buyPrice = $buyPricing['buy_price'];
        $processingCost = FinancialDecimal::mul((string) $cardValue, (string) config('giftcard.processing_cost_percent', 0.01), 8);

        return FinancialDecimal::sub(FinancialDecimal::sub($buyPrice, (string) $sellPriceReceived, 8), $processingCost, 8);
    }

    private function preview(string $operation, string $brand, string $amount, string $currency, array $context): array
    {
        $this->ensureDefaultRule($operation);

        return $this->pricing->preview([
            'product' => 'GIFTCARD',
            'operation' => $operation,
            'amount' => $amount,
            'currency' => $currency,
            'asset' => $currency,
            'market_symbol' => strtoupper($brand),
            'brand' => strtolower($brand),
            'provider' => strtolower((string) ($context['provider'] ?? $brand)),
            'denomination' => FinancialDecimal::normalize($amount, 8),
            'country' => $context['country'] ?? null,
            'promotion_code' => $context['promotion_code'] ?? null,
            'user_id' => $context['user_id'] ?? null,
        ]);
    }

    private function ensureDefaultRule(string $operation): void
    {
        if (PricingRule::query()->where('product', 'GIFTCARD')->where('operation', $operation)->where('status', 'ACTIVE')->exists()) {
            return;
        }

        $bps = match ($operation) {
            'BUY_MARKUP' => FinancialDecimal::mul(FinancialDecimal::sub((string) config('giftcard.default_markup_rate', '1.10'), '1', 8), '10000', 8),
            'PLATFORM_FEE' => FinancialDecimal::mul((string) config('giftcard.platform_fee_percent', '0.02'), '10000', 8),
            default => '0',
        };

        PricingRule::query()->create([
            'rule_uuid' => (string) Str::uuid(),
            'name' => "Giftcard {$operation} default policy",
            'product' => 'GIFTCARD',
            'operation' => $operation,
            'fee_type' => 'PERCENTAGE',
            'value' => '0',
            'fixed_value' => '0',
            'percentage_bps' => FinancialDecimal::normalize($bps, 8),
            'spread_bps' => '0',
            'precedence_scope' => 'PRODUCT_DEFAULT',
            'priority' => 0,
            'version' => 1,
            'status' => 'ACTIVE',
            'allow_negative' => false,
            'requires_maker_checker' => true,
            'metadata' => ['source' => 'giftcard_default_policy_bootstrap'],
        ]);

        $this->pricing->invalidateCache();
    }

    private function snapshot(array $decision, array $extra): array
    {
        return array_merge([
            'pricing_rule_id' => $decision['pricing_rule_id'] ?? null,
            'rule_version' => $decision['rule_version'] ?? null,
            'gross_amount' => $decision['gross_amount'] ?? null,
            'exaearn_fee' => $decision['fee_amount'] ?? null,
            'spread_bps' => $decision['spread_bps'] ?? null,
            'rate_bps' => $decision['rate_bps'] ?? null,
            'currency' => $decision['currency'] ?? null,
            'source' => $decision['source'] ?? 'PRICING_ENGINE',
            'captured_at' => now()->toIso8601String(),
        ], $extra);
    }
}
