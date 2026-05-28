<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use RuntimeException;

class GiftCardFeeCalculator
{
    private const SCALE = 8;

    /**
     * Calculate all fees and total cost for a giftcard purchase.
     * 
     * @param string $brand Gift card brand/provider name
     * @param float $cardValue Face value of the card
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
    public function calculateFees(string $brand, float $cardValue, string $currency = 'USD'): array
    {
        $provider = strtolower($brand);
        $providerConfig = $this->getProviderConfig($provider);

        // Calculate API and delivery fees
        $apiFeePct = $providerConfig['api_fee_percent'] ?? 0.02;
        $deliveryFeeFixed = $providerConfig['delivery_fee_fixed'] ?? 0.00;
        $feeStrategy = $providerConfig['user_fee_strategy'] ?? 'pass_to_user';

        $apiCost = bcmul((string) $cardValue, (string) $apiFeePct, self::SCALE);
        $totalApiCost = bcadd($apiCost, (string) $deliveryFeeFixed, self::SCALE);

        // Calculate user charge based on strategy
        $userCharge = $this->calculateUserCharge(
            $cardValue,
            (float) $totalApiCost,
            $feeStrategy,
            $providerConfig
        );

        $platformMarginPercent = (string) config('giftcards.fee_management.platform_margin_percent', 0.01);
        $minPlatformProfit = (string) config('giftcards.fee_management.min_platform_profit', 0.01);

        $absorbedFee = $this->calculateAbsorbedFee((float) $totalApiCost, $userCharge);
        $platformProfit = bcmul((string) $absorbedFee, $platformMarginPercent, self::SCALE);

        if (bccomp($platformProfit, $minPlatformProfit, self::SCALE) < 0) {
            $platformProfit = $minPlatformProfit;
        }

        $totalCost = bcadd((string) $cardValue, (string) $userCharge, self::SCALE);

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
                'api_fee_percent' => $apiFeePct * 100,
                'delivery_fee_fixed' => (float) $deliveryFeeFixed,
                'platform_margin_percent' => config('giftcards.fee_management.platform_margin_percent', 0.01) * 100,
                'note' => $this->describeStrategy($feeStrategy, $providerConfig),
            ],
        ];
    }

    /**
     * Calculate what the user should be charged based on fee strategy.
     */
    private function calculateUserCharge(float $cardValue, float $totalApiCost, string $strategy, array $providerConfig): float
    {
        return match ($strategy) {
            'pass_to_user' => $totalApiCost,
            'absorb' => 0.0,
            'split' => $this->calculateSplitFee($totalApiCost, $providerConfig),
            default => $totalApiCost,
        };
    }

    /**
     * Calculate split fee where platform absorbs part.
     */
    private function calculateSplitFee(float $totalApiCost, array $providerConfig): float
    {
        $splitRatio = $providerConfig['split_ratio'] ?? 0.5;
        return (float) bcmul((string) $totalApiCost, (string) $splitRatio, self::SCALE);
    }

    private function calculateAbsorbedFee(float $totalApiCost, float $userCharge): float
    {
        $absorbed = (float) bcsub((string) $totalApiCost, (string) $userCharge, self::SCALE);
        return max($absorbed, 0.0);
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
