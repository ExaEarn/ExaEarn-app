<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketMakerInventorySnapshot;
use App\Models\MarketMakerProfile;
use Illuminate\Support\Str;

class MarketMakerInventoryService
{
    public function __construct(
        private readonly InstitutionalService $institutions,
        private readonly BalanceProjectionService $balances,
        private readonly MarketMakerProgramService $program,
    ) {
    }

    public function snapshot(MarketMakerProfile $profile, string $marketSymbol): MarketMakerInventorySnapshot
    {
        [$base, $quote] = $this->program->assetsForSymbol($marketSymbol);
        $baseAccount = $this->institutions->canonicalSubaccountLedgerAccount($profile->subaccount_id, $base);
        $quoteAccount = $this->institutions->canonicalSubaccountLedgerAccount($profile->subaccount_id, $quote);
        $baseAvailable = $this->balances->accountProjection($baseAccount)['available'];
        $quoteAvailable = $this->balances->accountProjection($quoteAccount)['available'];
        $assignment = $profile->assignments()->where('market_symbol', strtoupper($marketSymbol))->where('status', 'ACTIVE')->latest()->first();
        $maxExposure = FinancialDecimal::normalize((string) ($assignment?->maximum_inventory ?? ($profile->limits['max_notional_per_market'] ?? '0')));
        $utilization = FinancialDecimal::compare($maxExposure, '0') > 0
            ? FinancialDecimal::mul(FinancialDecimal::div($quoteAvailable, $maxExposure, 18), '100', 8)
            : '0.00000000';
        $status = FinancialDecimal::compare($utilization, '100', 8) > 0 ? 'LIMIT_EXCEEDED' : (FinancialDecimal::compare($utilization, '80', 8) > 0 ? 'WATCH' : 'HEALTHY');

        return MarketMakerInventorySnapshot::query()->create([
            'snapshot_uuid' => (string) Str::uuid(),
            'market_maker_id' => $profile->id,
            'subaccount_id' => $profile->subaccount_id,
            'market_symbol' => strtoupper($marketSymbol),
            'base_asset' => $base,
            'quote_asset' => $quote,
            'current_base_inventory' => $baseAvailable,
            'current_quote_inventory' => $quoteAvailable,
            'target_base_inventory' => '0',
            'target_quote_inventory' => (string) ($assignment?->target_quote_size ?? '0'),
            'inventory_imbalance' => $quoteAvailable,
            'inventory_utilization' => $utilization,
            'net_delta' => $baseAvailable,
            'max_exposure' => $maxExposure,
            'status' => $status,
            'metadata' => ['source' => 'canonical_institutional_subaccount_ledger'],
            'measured_at' => now(),
        ]);
    }
}
