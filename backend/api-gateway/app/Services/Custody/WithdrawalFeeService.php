<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;
use RuntimeException;

class WithdrawalFeeService
{
    public function __construct(private readonly PricingPolicyEngine $pricing)
    {
    }

    public function quote(string $asset, string $network, string $amount): array
    {
        $amount = FinancialDecimal::normalize($amount);
        if (FinancialDecimal::compare($amount, '0') <= 0) {
            throw new RuntimeException('Withdrawal amount must be greater than zero.');
        }

        $networkFeeQuote = $this->quoteFee('NETWORK_FEE', $amount, $asset, $network, (string) config('custody.fees.default_network_fee', '0'));
        $platformFeeQuote = $this->quoteFee('PLATFORM_FEE', $amount, $asset, $network, (string) config('custody.fees.default_platform_fee', '0'));
        $networkFee = FinancialDecimal::normalize((string) $networkFeeQuote['fee_amount']);
        $platformFee = FinancialDecimal::normalize((string) $platformFeeQuote['fee_amount']);
        $maxNetworkFee = FinancialDecimal::normalize((string) config('custody.fees.max_network_fee', '1000'));
        if (FinancialDecimal::compare($networkFee, $maxNetworkFee) > 0) {
            throw new RuntimeException('Estimated network fee exceeds policy maximum.');
        }

        $totalDebit = FinancialDecimal::add($amount, FinancialDecimal::add($networkFee, $platformFee));

        return [
            'asset' => strtoupper($asset),
            'network' => strtolower($network),
            'amount' => $amount,
            'network_fee' => $networkFee,
            'platform_fee' => $platformFee,
            'total_debit' => $totalDebit,
            'pricing_decisions' => [
                'network_fee' => $networkFeeQuote,
                'platform_fee' => $platformFeeQuote,
            ],
        ];
    }

    private function quoteFee(string $operation, string $amount, string $asset, string $network, string $legacyFixedFee): array
    {
        try {
            return $this->pricing->preview([
                'product' => 'WITHDRAWAL',
                'operation' => $operation,
                'amount' => $amount,
                'asset' => strtoupper($asset),
                'currency' => strtoupper($asset),
                'network' => strtolower($network),
            ]);
        } catch (\Throwable $exception) {
            if ($this->pricing->isEnforced('WITHDRAWAL')) {
                throw $exception;
            }

            return [
                'source' => 'legacy_custody_config',
                'gross_amount' => FinancialDecimal::normalize($amount),
                'fee_amount' => FinancialDecimal::normalize($legacyFixedFee),
                'net_amount' => FinancialDecimal::sub($amount, $legacyFixedFee),
                'fixed_fee' => FinancialDecimal::normalize($legacyFixedFee),
                'fee_policy_snapshot' => ['source' => 'config/custody.php', 'operation' => $operation],
            ];
        }
    }
}
