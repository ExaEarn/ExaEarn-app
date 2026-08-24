<?php

declare(strict_types=1);

namespace App\Domain\P2P\Services;

use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;

class P2PFeeService
{
    public function __construct(private readonly PricingPolicyEngine $pricing)
    {
    }

    public function quote(string $asset, string $amount, string $side = 'taker'): array
    {
        $side = strtolower($side);
        $rate = (string) config("p2p.fees.{$side}", config('p2p.fees.taker', '0'));
        $fee = FinancialDecimal::mul($amount, $rate);

        try {
            $quote = $this->pricing->preview([
                'product' => 'P2P',
                'operation' => strtoupper($side).'_FEE',
                'amount' => $amount,
                'asset' => strtoupper($asset),
                'currency' => strtoupper($asset),
            ]);

            return [
                'fee_asset' => strtoupper($asset),
                'fee_rate' => FinancialDecimal::div((string) $quote['rate_bps'], '10000'),
                'fee_amount' => $quote['fee_amount'],
                'net_amount' => $quote['net_amount'],
                'pricing_decision' => $quote,
            ];
        } catch (\Throwable $exception) {
            if ($this->pricing->isEnforced('P2P')) {
                throw $exception;
            }

            return [
                'fee_asset' => strtoupper($asset),
                'fee_rate' => FinancialDecimal::normalize($rate),
                'fee_amount' => $fee,
                'net_amount' => FinancialDecimal::sub($amount, $fee),
                'pricing_decision' => ['source' => 'legacy_p2p_config'],
            ];
        }
    }
}
