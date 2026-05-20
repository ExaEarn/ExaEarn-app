<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiquidityLog;
use App\Models\TreasuryAccount;
use Illuminate\Support\Facades\Log;

class LiquidityService
{
    private const SCALE = 18;

    private array $minimumThresholds = [
        'nomba' => ['NGN' => '50000'], // ₦50,000 minimum
        'monnify' => ['NGN' => '75000'], // ₦75,000 minimum
        'paystack' => [
            'NGN' => '100000', // ₦100,000 minimum
            'ZAR' => '5000',   // R5,000 minimum
            'USD' => '1000',   // $1,000 minimum
        ],
        'flutterwave' => [
            'NGN' => '80000',  // ₦80,000 minimum
            'ZAR' => '4000',   // R4,000 minimum
            'USD' => '800',    // $800 minimum
        ],
    ];

    private array $maximumThresholds = [
        'nomba' => ['NGN' => '5000000'], // ₦5M maximum
        'monnify' => ['NGN' => '7500000'], // ₦7.5M maximum
        'paystack' => [
            'NGN' => '10000000', // ₦10M maximum
            'ZAR' => '500000',   // R500K maximum
            'USD' => '100000',   // $100K maximum
        ],
        'flutterwave' => [
            'NGN' => '8000000',  // ₦8M maximum
            'ZAR' => '400000',   // R400K maximum
            'USD' => '80000',    // $80K maximum
        ],
    ];

    public function __construct(
        private readonly TreasuryService $treasury,
    ) {
    }

    /**
     * Detect low liquidity across providers
     */
    public function detectLowLiquidity(): array
    {
        $alerts = [];

        foreach ($this->minimumThresholds as $provider => $currencies) {
            foreach ($currencies as $currency => $minThreshold) {
                $balance = $this->treasury->getBalance($provider, $currency);

                if (bccomp($balance, $minThreshold, self::SCALE) < 0) {
                    $alerts[] = [
                        'provider' => $provider,
                        'currency' => $currency,
                        'current_balance' => $balance,
                        'minimum_threshold' => $minThreshold,
                        'deficit' => bcsub($minThreshold, $balance, self::SCALE),
                        'severity' => 'critical',
                    ];
                }
            }
        }

        return $alerts;
    }

    /**
     * Auto-trigger rebalancing when thresholds are breached
     */
    public function autoTriggerRebalance(): array
    {
        $actions = [];
        $lowLiquidityAlerts = $this->detectLowLiquidity();

        foreach ($lowLiquidityAlerts as $alert) {
            $rebalanceAction = $this->findRebalanceSource($alert);

            if ($rebalanceAction) {
                try {
                    $this->rebalance(
                        $rebalanceAction['from_provider'],
                        $alert['provider'],
                        $rebalanceAction['amount'],
                        $alert['currency']
                    );

                    $actions[] = [
                        'type' => 'auto_rebalance',
                        'from_provider' => $rebalanceAction['from_provider'],
                        'to_provider' => $alert['provider'],
                        'amount' => $rebalanceAction['amount'],
                        'currency' => $alert['currency'],
                        'status' => 'success',
                    ];
                } catch (\Exception $e) {
                    $actions[] = [
                        'type' => 'auto_rebalance',
                        'from_provider' => $rebalanceAction['from_provider'],
                        'to_provider' => $alert['provider'],
                        'amount' => $rebalanceAction['amount'],
                        'currency' => $alert['currency'],
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $actions;
    }

    /**
     * Manual rebalance between providers
     */
    public function rebalance(string $fromProvider, string $toProvider, string $amount, string $currency): void
    {
        // Validate providers and currency support
        $this->validateRebalance($fromProvider, $toProvider, $amount, $currency);

        // Check source has sufficient funds
        $sourceBalance = $this->treasury->getBalance($fromProvider, $currency);
        if (bccomp($sourceBalance, $amount, self::SCALE) < 0) {
            throw new \RuntimeException("Insufficient balance in {$fromProvider} for rebalance");
        }

        // Perform the rebalance
        $reference = 'rebalance_' . uniqid();

        $this->treasury->debit($fromProvider, $amount, $currency, $reference . '_debit');
        $this->treasury->credit($toProvider, $amount, $currency, $reference . '_credit');

        // Log the rebalance action
        LiquidityLog::create([
            'provider' => $fromProvider,
            'action' => 'rebalance',
            'details' => [
                'direction' => 'outbound',
                'to_provider' => $toProvider,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'source_balance_after' => $this->treasury->getBalance($fromProvider, $currency),
                'destination_balance_after' => $this->treasury->getBalance($toProvider, $currency),
            ],
        ]);

        LiquidityLog::create([
            'provider' => $toProvider,
            'action' => 'rebalance',
            'details' => [
                'direction' => 'inbound',
                'from_provider' => $fromProvider,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
            ],
        ]);

        Log::info("Liquidity rebalance: {$fromProvider} -> {$toProvider} {$amount} {$currency}");
    }

    /**
     * Find a source provider for rebalancing
     */
    private function findRebalanceSource(array $alert): ?array
    {
        $neededAmount = $alert['deficit'];
        $currency = $alert['currency'];

        // Find providers with excess liquidity for this currency
        $excessProviders = [];

        foreach ($this->maximumThresholds as $provider => $currencies) {
            if (!isset($currencies[$currency])) {
                continue;
            }

            $maxThreshold = $currencies[$currency];
            $currentBalance = $this->treasury->getBalance($provider, $currency);

            if (bccomp($currentBalance, $maxThreshold, self::SCALE) > 0) {
                $excessAmount = bcsub($currentBalance, $maxThreshold, self::SCALE);
                $transferAmount = min($excessAmount, $neededAmount);

                $excessProviders[] = [
                    'provider' => $provider,
                    'available_excess' => $excessAmount,
                    'transfer_amount' => $transferAmount,
                ];
            }
        }

        // Sort by available excess (prefer larger excess)
        usort($excessProviders, fn($a, $b) => bccomp($b['available_excess'], $a['available_excess'], self::SCALE));

        return $excessProviders[0] ?? null;
    }

    /**
     * Validate rebalance operation
     */
    private function validateRebalance(string $fromProvider, string $toProvider, string $amount, string $currency): void
    {
        if ($fromProvider === $toProvider) {
            throw new \RuntimeException('Cannot rebalance to the same provider');
        }

        // Check if providers support the currency
        $fromConfig = $this->minimumThresholds[$fromProvider] ?? [];
        $toConfig = $this->minimumThresholds[$toProvider] ?? [];

        if (!isset($fromConfig[$currency])) {
            throw new \RuntimeException("{$fromProvider} does not support {$currency}");
        }

        if (!isset($toConfig[$currency])) {
            throw new \RuntimeException("{$toProvider} does not support {$currency}");
        }

        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw new \RuntimeException('Rebalance amount must be positive');
        }
    }

    /**
     * Get liquidity status for all providers
     */
    public function getLiquidityStatus(): array
    {
        $status = [];

        foreach ($this->minimumThresholds as $provider => $currencies) {
            foreach ($currencies as $currency => $minThreshold) {
                $currentBalance = $this->treasury->getBalance($provider, $currency);
                $maxThreshold = $this->maximumThresholds[$provider][$currency] ?? '0';

                $status[] = [
                    'provider' => $provider,
                    'currency' => $currency,
                    'current_balance' => $currentBalance,
                    'minimum_threshold' => $minThreshold,
                    'maximum_threshold' => $maxThreshold,
                    'status' => $this->getLiquidityStatusForAccount($currentBalance, $minThreshold, $maxThreshold),
                ];
            }
        }

        return $status;
    }

    /**
     * Get liquidity status for a single account
     */
    private function getLiquidityStatusForAccount(string $balance, string $minThreshold, string $maxThreshold): string
    {
        if (bccomp($balance, $minThreshold, self::SCALE) < 0) {
            return 'critical';
        }

        if (bccomp($balance, $maxThreshold, self::SCALE) > 0) {
            return 'excess';
        }

        return 'optimal';
    }

    /**
     * Log threshold breach
     */
    public function logThresholdBreach(string $provider, string $currency, string $currentBalance, string $threshold, string $type): void
    {
        LiquidityLog::create([
            'provider' => $provider,
            'action' => 'threshold_breach',
            'details' => [
                'currency' => $currency,
                'current_balance' => $currentBalance,
                'threshold' => $threshold,
                'breach_type' => $type, // 'low' or 'high'
            ],
        ]);
    }
}