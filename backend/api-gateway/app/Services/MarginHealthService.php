<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\MarginAccount;
use App\Models\MarginAssetConfig;
use App\Models\MarginLoan;

class MarginHealthService
{
    public function __construct(
        private readonly MarginAccountService $accounts,
        private readonly MarginPricingService $pricing,
    ) {
    }

    public function health(MarginAccount $account, array $overrides = []): array
    {
        $accountType = $this->accounts->ledgerAccountType($account);
        $balances = Account::query()
            ->where('user_id', $account->user_id)
            ->where('account_type', $accountType)
            ->get();

        $grossAssets = '0';
        $adjustedCollateral = '0';
        $assets = [];

        foreach ($balances as $balance) {
            $asset = strtoupper((string) $balance->asset);
            $amount = (string) $balance->balance;
            if (isset($overrides['balances'][$asset])) {
                $amount = FinancialDecimal::add($amount, (string) $overrides['balances'][$asset]);
            }
            if (FinancialDecimal::compare($amount, '0') <= 0) {
                continue;
            }

            $config = MarginAssetConfig::query()->where('asset', $asset)->first();
            $value = $this->pricing->value($asset, $amount);
            $grossAssets = FinancialDecimal::add($grossAssets, $value);
            $collateralFactor = $config?->collateral_enabled ? (string) $config->collateral_factor : '0';
            $riskValue = FinancialDecimal::mul($value, $collateralFactor);
            $adjustedCollateral = FinancialDecimal::add($adjustedCollateral, $riskValue);
            $assets[] = [
                'asset' => $asset,
                'amount' => $amount,
                'market_value' => $value,
                'collateral_factor' => $collateralFactor,
                'adjusted_collateral' => $riskValue,
            ];
        }

        foreach ((array) ($overrides['new_assets'] ?? []) as $asset => $amount) {
            $asset = strtoupper((string) $asset);
            $amount = (string) $amount;
            $config = MarginAssetConfig::query()->where('asset', $asset)->first();
            $value = $this->pricing->value($asset, $amount);
            $grossAssets = FinancialDecimal::add($grossAssets, $value);
            $collateralFactor = $config?->collateral_enabled ? (string) $config->collateral_factor : '0';
            $riskValue = FinancialDecimal::mul($value, $collateralFactor);
            $adjustedCollateral = FinancialDecimal::add($adjustedCollateral, $riskValue);
        }

        $grossLiabilities = '0';
        $liabilities = [];
        $loans = MarginLoan::query()
            ->where('margin_account_id', $account->id)
            ->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID, MarginLoan::STATUS_LIQUIDATING])
            ->get();

        foreach ($loans as $loan) {
            $debt = FinancialDecimal::add((string) $loan->principal, (string) $loan->accrued_interest);
            if (isset($overrides['new_debt'][strtoupper((string) $loan->asset)])) {
                $debt = FinancialDecimal::add($debt, (string) $overrides['new_debt'][strtoupper((string) $loan->asset)]);
            }
            $value = $this->pricing->value((string) $loan->asset, $debt);
            $grossLiabilities = FinancialDecimal::add($grossLiabilities, $value);
            $liabilities[] = [
                'asset' => strtoupper((string) $loan->asset),
                'debt' => $debt,
                'market_value' => $value,
            ];
        }

        foreach ((array) ($overrides['new_debt'] ?? []) as $asset => $amount) {
            $matched = $loans->contains(fn (MarginLoan $loan): bool => strtoupper((string) $loan->asset) === strtoupper((string) $asset));
            if ($matched) {
                continue;
            }
            $grossLiabilities = FinancialDecimal::add($grossLiabilities, $this->pricing->value((string) $asset, (string) $amount));
        }

        $healthFactor = FinancialDecimal::compare($grossLiabilities, '0') === 0
            ? '999999.000000000000000000'
            : FinancialDecimal::div($adjustedCollateral, $grossLiabilities);
        $marginLevel = FinancialDecimal::compare($grossLiabilities, '0') === 0
            ? '999999.000000000000000000'
            : FinancialDecimal::div($grossAssets, $grossLiabilities);
        $equity = FinancialDecimal::sub($grossAssets, $grossLiabilities);

        return [
            'account_uuid' => $account->account_uuid,
            'mode' => $account->mode,
            'market_symbol' => $account->market_symbol,
            'gross_asset_value' => $grossAssets,
            'adjusted_collateral_value' => $adjustedCollateral,
            'gross_liability_value' => $grossLiabilities,
            'equity' => $equity,
            'health_factor' => $healthFactor,
            'margin_level' => $marginLevel,
            'status' => $this->riskStatus($healthFactor),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'thresholds' => config('margin.health'),
        ];
    }

    public function assertProjectedBorrowAllowed(MarginAccount $account, string $asset, string $amount): void
    {
        $health = $this->health($account, [
            'new_assets' => [strtoupper($asset) => $amount],
            'new_debt' => [strtoupper($asset) => $amount],
        ]);

        if (FinancialDecimal::compare($health['health_factor'], (string) config('margin.health.initial_borrow_min')) < 0) {
            throw new \RuntimeException('Projected margin health is below the initial borrow requirement.');
        }
    }

    public function assertTransferOutAllowed(MarginAccount $account, string $asset, string $amount): void
    {
        $health = $this->health($account, [
            'balances' => [strtoupper($asset) => FinancialDecimal::sub('0', $amount)],
        ]);

        if (FinancialDecimal::compare($health['health_factor'], (string) config('margin.health.borrow_disabled')) < 0) {
            throw new \RuntimeException('Transfer would make the margin account unsafe.');
        }
    }

    private function riskStatus(string $healthFactor): string
    {
        if (FinancialDecimal::compare($healthFactor, (string) config('margin.health.liquidation')) < 0) {
            return 'LIQUIDATION_PENDING';
        }
        if (FinancialDecimal::compare($healthFactor, (string) config('margin.health.borrow_disabled')) < 0) {
            return 'BORROW_DISABLED';
        }
        if (FinancialDecimal::compare($healthFactor, (string) config('margin.health.warning')) < 0) {
            return 'WARNING';
        }

        return 'HEALTHY';
    }
}
