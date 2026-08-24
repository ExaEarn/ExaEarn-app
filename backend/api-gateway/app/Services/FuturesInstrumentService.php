<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesMarket;
use RuntimeException;

class FuturesInstrumentService
{
    public function specification(FuturesMarket $market): array
    {
        $symbol = strtoupper((string) $market->symbol);
        $base = strtoupper((string) ($market->base_asset ?: preg_replace('/USDT$/', '', $symbol)));

        return [
            'symbol' => $symbol,
            'underlying' => $base,
            'base_asset' => $base,
            'quote_asset' => strtoupper((string) ($market->quote_asset ?: 'USDT')),
            'settlement_asset' => strtoupper((string) ($market->settlement_asset ?: 'USDT')),
            'contract_type' => strtoupper((string) ($market->contract_type ?: 'PERPETUAL')),
            'tick_size' => (string) ($market->tick_size ?: '0.01'),
            'quantity_step' => (string) ($market->quantity_step ?: '0.0001'),
            'min_quantity' => (string) ($market->min_quantity ?: '0.0001'),
            'max_quantity' => (string) ($market->max_quantity ?: '100'),
            'min_notional' => (string) ($market->min_notional ?: '5'),
            'max_notional' => (string) ($market->max_notional ?: config('futures.max_position_notional', '1000000')),
            'min_leverage' => (int) $market->min_leverage,
            'max_leverage' => (int) $market->max_leverage,
            'funding_interval_hours' => (int) config('futures.funding_interval_hours', 8),
            'maintenance_margin_rate' => (string) ($market->maintenance_margin_rate ?: '0.005'),
            'risk_tiers' => $this->riskTiers($market),
            'status' => (string) $market->status,
            'engine_mode' => (string) ($market->engine_mode ?: 'legacy'),
        ];
    }

    public function riskTiers(FuturesMarket $market): array
    {
        $tiers = $market->risk_tiers;
        if (is_array($tiers) && $tiers !== []) {
            return $tiers;
        }

        return config('futures.default_risk_tiers', [
            ['notional_floor' => '0', 'notional_cap' => '50000', 'maintenance_margin_rate' => '0.005', 'maintenance_amount' => '0', 'max_leverage' => 100],
            ['notional_floor' => '50000', 'notional_cap' => '250000', 'maintenance_margin_rate' => '0.01', 'maintenance_amount' => '250', 'max_leverage' => 50],
            ['notional_floor' => '250000', 'notional_cap' => '1000000', 'maintenance_margin_rate' => '0.025', 'maintenance_amount' => '4000', 'max_leverage' => 20],
        ]);
    }

    public function tierForNotional(FuturesMarket $market, string $notional): array
    {
        foreach ($this->riskTiers($market) as $tier) {
            $floor = (string) ($tier['notional_floor'] ?? '0');
            $cap = (string) ($tier['notional_cap'] ?? '0');
            if (FinancialDecimal::compare($notional, $floor) >= 0 && (FinancialDecimal::compare($cap, '0') === 0 || FinancialDecimal::compare($notional, $cap) <= 0)) {
                return $tier;
            }
        }

        throw new RuntimeException('Futures position exceeds configured risk tiers.');
    }

    public function assertIncrement(string $value, string $step, string $message): void
    {
        if (FinancialDecimal::compare($step, '0') <= 0) {
            return;
        }

        $ratio = FinancialDecimal::div($value, $step, 8);
        if (FinancialDecimal::compare($ratio, FinancialDecimal::normalize($ratio, 0), 8) !== 0) {
            throw new RuntimeException($message);
        }
    }
}
