<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\PricingRule;
use RuntimeException;
use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;
use Illuminate\Support\Str;

class GiftCardFeeCalculator
{
    private const SCALE = 8;

    public function __construct(private readonly PricingPolicyEngine $pricing)
    {
    }

    /**
     * Calculate all fees and total cost for a giftcard purchase.
     * 
     * @param string $brand Gift card brand/provider name
     * @param int|float|string $cardValue Face value of the card
     * @param string $currency Target currency (e.g., USD, USDT)
     * @return array {
     *     'card_value': float,
     *     'api_fee': float,
     *     'delivery_fee': float,
     *     'platform_fee': float,
     *     'user_charge': float,
     *     'total_cost': float,
     *     'platform_profit': float,
     *     'fee_breakdown': array
     * }
     */
    public function calculateFees(string $brand, int|float|string $cardValue, string $currency = 'USD'): array
    {
        $provider = strtolower($brand);
        $providerConfig = $this->getProviderConfig($provider);
        $cardValue = $this->decimal($cardValue);

        // Calculate API and delivery fees
        $apiFeePct = $this->decimal($providerConfig['api_fee_percent'] ?? '0.02');
        $deliveryFeeFixed = $this->decimal($providerConfig['delivery_fee_fixed'] ?? '0');
        $feeStrategy = $providerConfig['user_fee_strategy'] ?? 'pass_to_user';

        $apiCost = bcmul($cardValue, $apiFeePct, self::SCALE);
        $totalApiCost = bcadd($apiCost, $deliveryFeeFixed, self::SCALE);

        $legacyUserCharge = $this->calculateUserCharge(
            $cardValue,
            $totalApiCost,
            $feeStrategy,
            $providerConfig
        );
        $pricingDecision = $this->centralPricing('PLATFORM_FEE', $provider, $cardValue, $currency);
        $userCharge = FinancialDecimal::max($legacyUserCharge, FinancialDecimal::normalize((string) $pricingDecision['fee_amount'], self::SCALE), self::SCALE);

        $platformMarginPercent = (string) config('giftcards.fee_management.platform_margin_percent', 0.01);
        $minPlatformProfit = (string) config('giftcards.fee_management.min_platform_profit', 0.01);

        $absorbedFee = $this->calculateAbsorbedFee($totalApiCost, $userCharge);
        $platformProfit = bcmul($absorbedFee, $platformMarginPercent, self::SCALE);

        if (bccomp($platformProfit, $minPlatformProfit, self::SCALE) < 0) {
            $platformProfit = $minPlatformProfit;
        }

        $totalCost = bcadd($cardValue, $userCharge, self::SCALE);

        return [
            'card_value' => (float) $cardValue,
            'api_fee' => (float) $apiCost,
            'delivery_fee' => (float) $deliveryFeeFixed,
            'user_charge' => (float) $userCharge,  // What user pays (fees only)
            'platform_fee' => (float) $platformProfit,
            'total_cost_to_user' => (float) $totalCost,  // card_value + user_charge
            'platform_profit' => (float) $platformProfit,
            'total_api_cost' => (float) $totalApiCost,
            'currency' => strtoupper($currency),
            'fee_breakdown' => [
                'strategy' => $feeStrategy,
                'api_fee_percent' => (float) bcmul($apiFeePct, '100', self::SCALE),
                'delivery_fee_fixed' => (float) $deliveryFeeFixed,
                'platform_margin_percent' => config('giftcards.fee_management.platform_margin_percent', 0.01) * 100,
                'note' => $this->describeStrategy($feeStrategy, $providerConfig),
                'central_pricing' => [
                    'pricing_rule_id' => $pricingDecision['pricing_rule_id'] ?? null,
                    'rule_version' => $pricingDecision['rule_version'] ?? null,
                    'source' => $pricingDecision['source'] ?? 'PRICING_ENGINE',
                    'gross_amount' => $pricingDecision['gross_amount'] ?? $cardValue,
                    'fee_amount' => $pricingDecision['fee_amount'] ?? '0',
                    'captured_at' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * Calculate what the user should be charged based on fee strategy.
     */
    private function calculateUserCharge(string $cardValue, string $totalApiCost, string $strategy, array $providerConfig): string
    {
        return match ($strategy) {
            'pass_to_user' => $totalApiCost,
            'absorb' => '0',
            'split' => $this->calculateSplitFee($totalApiCost, $providerConfig),
            default => $totalApiCost,
        };
    }

    /**
     * Calculate split fee where platform absorbs part.
     */
    private function calculateSplitFee(string $totalApiCost, array $providerConfig): string
    {
        $splitRatio = $this->decimal($providerConfig['split_ratio'] ?? '0.5');
        return bcmul($totalApiCost, $splitRatio, self::SCALE);
    }

    private function calculateAbsorbedFee(string $totalApiCost, string $userCharge): string
    {
        $absorbed = bcsub($totalApiCost, $userCharge, self::SCALE);
        return bccomp($absorbed, '0', self::SCALE) < 0 ? '0' : $absorbed;
    }

    private function decimal(int|float|string $value): string
    {
        return FinancialDecimal::normalize((string) $value, self::SCALE);
    }

    private function centralPricing(string $operation, string $brand, string $amount, string $currency): array
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
            'provider' => strtolower($brand),
            'denomination' => $amount,
        ]);
    }

    private function ensureDefaultRule(string $operation): void
    {
        if (PricingRule::query()->where('product', 'GIFTCARD')->where('operation', $operation)->where('status', 'ACTIVE')->exists()) {
            return;
        }

        $bps = $operation === 'PLATFORM_FEE'
            ? FinancialDecimal::mul((string) config('giftcard.platform_fee_percent', '0.02'), '10000', self::SCALE)
            : '0';

        PricingRule::query()->create([
            'rule_uuid' => (string) Str::uuid(),
            'name' => "Giftcard {$operation} default policy",
            'product' => 'GIFTCARD',
            'operation' => $operation,
            'fee_type' => 'PERCENTAGE',
            'value' => '0',
            'fixed_value' => '0',
            'percentage_bps' => FinancialDecimal::normalize($bps, self::SCALE),
            'spread_bps' => '0',
            'precedence_scope' => 'PRODUCT_DEFAULT',
            'priority' => 0,
            'version' => 1,
            'status' => 'ACTIVE',
            'allow_negative' => false,
            'requires_maker_checker' => true,
            'metadata' => ['source' => 'giftcard_fee_default_policy_bootstrap'],
        ]);

        $this->pricing->invalidateCache();
    }

    /**
     * Get provider configuration with defaults.
     */
    private function getProviderConfig(string $provider): array
    {
        $config = config("giftcards.providers.{$provider}");
        
        if (!$config) {
            throw new RuntimeException("Unknown gift card provider: {$provider}");
        }

        return array_merge([
            'verified_source' => false,
            'api_fee_percent' => 0.02,
            'delivery_fee_fixed' => 0.00,
            'user_fee_strategy' => 'pass_to_user',
            'split_ratio' => 0.5,
        ], $config);
    }

    /**
     * Human-readable description of fee strategy.
     */
    private function describeStrategy(string $strategy, array $config): string
    {
        return match ($strategy) {
            'pass_to_user' => 'Full API fees passed to user',
            'absorb' => 'Platform absorbs all API fees',
            'split' => sprintf(
                'Platform absorbs %.0f%%, user pays %.0f%%',
                (1 - ($config['split_ratio'] ?? 0.5)) * 100,
                ($config['split_ratio'] ?? 0.5) * 100
            ),
            default => 'Unknown strategy',
        };
    }

    /**
     * Batch calculate fees for multiple orders.
     */
    public function calculateBatchFees(array $orders): array
    {
        return array_map(
            fn ($order) => $this->calculateFees($order['brand'], $order['card_value'], $order['currency'] ?? 'USD'),
            $orders
        );
    }
}
