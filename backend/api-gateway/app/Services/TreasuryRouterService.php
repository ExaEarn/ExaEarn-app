<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TreasuryAccount;

class TreasuryRouterService
{
    private const SCALE = 18;

    private array $providerConfig = [
        'nomba' => [
            'currencies' => ['NGN'],
            'fee_structure' => ['NGN' => '0.015'], // 1.5%
            'uptime_threshold' => 0.95,
            'priority' => 1,
        ],
        'monnify' => [
            'currencies' => ['NGN'],
            'fee_structure' => ['NGN' => '0.01'], // 1%
            'uptime_threshold' => 0.98,
            'priority' => 2,
        ],
        'paystack' => [
            'currencies' => ['NGN', 'ZAR', 'USD'],
            'fee_structure' => [
                'NGN' => '0.015',
                'ZAR' => '0.025',
                'USD' => '0.03',
            ],
            'uptime_threshold' => 0.99,
            'priority' => 3,
        ],
        'flutterwave' => [
            'currencies' => ['NGN', 'ZAR', 'USD'],
            'fee_structure' => [
                'NGN' => '0.02',
                'ZAR' => '0.035',
                'USD' => '0.025',
            ],
            'uptime_threshold' => 0.97,
            'priority' => 4,
        ],
    ];

    /**
     * Select best provider for withdrawal
     */
    public function selectProvider(string $currency, string $amount, ?string $preferredProvider = null): array
    {
        $candidates = $this->getEligibleProviders($currency, $amount);

        if (empty($candidates)) {
            throw new \RuntimeException("No eligible providers for {$currency} withdrawal");
        }

        // If preferred provider is specified and eligible, use it
        if ($preferredProvider && isset($candidates[$preferredProvider])) {
            return $candidates[$preferredProvider];
        }

        // Select best provider based on criteria
        $bestProvider = null;
        $bestScore = -1;

        foreach ($candidates as $provider => $data) {
            $score = $this->calculateProviderScore($provider, $data, $currency, $amount);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProvider = $provider;
            }
        }

        if (!$bestProvider) {
            throw new \RuntimeException("Could not select provider for {$currency} withdrawal");
        }

        return $candidates[$bestProvider];
    }

    /**
     * Get providers eligible for the withdrawal
     */
    private function getEligibleProviders(string $currency, string $amount): array
    {
        $eligible = [];

        foreach ($this->providerConfig as $provider => $config) {
            // Check if provider supports currency
            if (!in_array($currency, $config['currencies'])) {
                continue;
            }

            // Check if provider has sufficient balance
            $balance = $this->getProviderBalance($provider, $currency);
            if (bccomp($balance, $amount, self::SCALE) < 0) {
                continue;
            }

            // Check provider health
            if (!$this->isProviderHealthy($provider)) {
                continue;
            }

            $fee = $this->calculateFee($provider, $currency, $amount);

            $eligible[$provider] = [
                'provider' => $provider,
                'available_balance' => $balance,
                'estimated_fee' => $fee,
                'reason' => 'Selected based on balance, fees, and health',
                'priority' => $config['priority'],
            ];
        }

        return $eligible;
    }

    /**
     * Calculate provider score for selection
     */
    private function calculateProviderScore(string $provider, array $data, string $currency, string $amount): float
    {
        $config = $this->providerConfig[$provider];

        // Base score from priority (lower priority number = higher score)
        $score = 100 - ($config['priority'] * 10);

        // Balance factor (prefer providers with more available balance)
        $balanceRatio = bcdiv($data['available_balance'], $amount, self::SCALE);
        $balanceScore = min((float) $balanceRatio * 10, 50); // Cap at 50 points
        $score += $balanceScore;

        // Fee factor (prefer lower fees)
        $feeRate = (float) $config['fee_structure'][$currency];
        $feeScore = (1 - $feeRate) * 20; // Lower fee = higher score
        $score += $feeScore;

        // Health factor
        $healthScore = $this->isProviderHealthy($provider) ? 20 : 0;
        $score += $healthScore;

        return $score;
    }

    /**
     * Get provider balance
     */
    private function getProviderBalance(string $provider, string $currency): string
    {
        $account = TreasuryAccount::where('provider', $provider)
            ->where('currency', $currency)
            ->where('status', 'active')
            ->first();

        return $account ? (string) $account->available_balance : '0';
    }

    /**
     * Check if provider is healthy
     */
    private function isProviderHealthy(string $provider): bool
    {
        $config = $this->providerConfig[$provider];

        // Simple health check - in production, this would check actual uptime metrics
        // For now, assume all providers are healthy
        return true;
    }

    /**
     * Calculate transaction fee
     */
    private function calculateFee(string $provider, string $currency, string $amount): string
    {
        $config = $this->providerConfig[$provider];
        $feeRate = (string) ($config['fee_structure'][$currency] ?? '0');

        return bcmul($amount, $feeRate, self::SCALE);
    }

    /**
     * Get provider capabilities
     */
    public function getProviderCapabilities(string $provider): array
    {
        return $this->providerConfig[$provider] ?? [];
    }

    /**
     * Get all supported providers for currency
     */
    public function getSupportedProviders(string $currency): array
    {
        $supported = [];

        foreach ($this->providerConfig as $provider => $config) {
            if (in_array($currency, $config['currencies'])) {
                $supported[] = $provider;
            }
        }

        return $supported;
    }
}