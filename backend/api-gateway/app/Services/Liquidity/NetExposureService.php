<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\Account;
use App\Models\ExternalVenueBalance;
use App\Models\NetExposureSnapshot;
use App\Models\WithdrawalLiquidityReserve;
use App\Services\FinancialDecimal;
use Illuminate\Support\Str;

class NetExposureService
{
    public function calculate(string $asset): NetExposureSnapshot
    {
        $asset = strtoupper($asset);
        $liability = FinancialDecimal::abs((string) Account::query()
            ->whereNotNull('user_id')
            ->where('asset', $asset)
            ->sum('balance'));
        $treasury = FinancialDecimal::normalize((string) Account::query()
            ->whereNull('user_id')
            ->whereIn('account_type', ['treasury', 'system_treasury', 'insurance_fund'])
            ->where('asset', $asset)
            ->sum('balance'));
        $external = FinancialDecimal::normalize((string) ExternalVenueBalance::query()->where('asset', $asset)->sum('available'));
        $reserve = WithdrawalLiquidityReserve::query()->where('asset', $asset)->first();
        $withdrawalReserve = $reserve ? (string) $reserve->minimum_reserve : '0';
        $controlled = FinancialDecimal::add($treasury, $external);
        $net = FinancialDecimal::sub($controlled, $liability);
        $coverage = FinancialDecimal::compare($liability, '0') > 0 ? FinancialDecimal::div($controlled, $liability) : '0';
        $status = FinancialDecimal::compare($controlled, $liability) >= 0 ? 'BACKED' : 'UNDER_BACKED';

        return NetExposureSnapshot::query()->create([
            'snapshot_id' => (string) Str::uuid(),
            'asset' => $asset,
            'user_liability' => $liability,
            'treasury_assets' => $treasury,
            'external_venue_exposure' => $external,
            'reserved_withdrawal_liquidity' => $withdrawalReserve,
            'net_exposure' => $net,
            'coverage_ratio' => $coverage,
            'status' => $status,
            'metadata' => ['controlled_backing' => $controlled],
            'calculated_at' => now(),
        ]);
    }
}
