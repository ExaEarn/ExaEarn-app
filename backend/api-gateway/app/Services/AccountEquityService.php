<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CollateralConfiguration;
use App\Models\FuturesPosition;
use App\Models\MarginAccount;
use App\Models\MarginLoan;
use App\Models\TradingExposureSnapshot;
use Illuminate\Support\Str;

class AccountEquityService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BalanceProjectionService $balances,
        private readonly MarginAccountService $marginAccounts,
    ) {
    }

    public function userEquity(int $userId): array
    {
        $assets = [];
        foreach (['USDT', 'USDC', 'BTC', 'ETH'] as $asset) {
            $funding = $this->ledger->getBalance($userId, $asset, 'funding');
            $spot = $this->ledger->getBalance($userId, $asset, 'unified_trading');
            $margin = $this->ledger->getBalance($userId, $asset, 'margin_cross');
            $futures = $this->ledger->getBalance($userId, $asset, 'futures');
            $total = FinancialDecimal::add(FinancialDecimal::add($funding, $spot), FinancialDecimal::add($margin, $futures));
            if (FinancialDecimal::compare($total, '0') === 0) {
                continue;
            }

            $factor = $this->collateralFactor($asset);
            $collateralValue = FinancialDecimal::mul($this->valueInUsdt($asset, $total), $factor);
            $assets[$asset] = [
                'asset' => $asset,
                'total' => $total,
                'funding' => $funding,
                'spot' => $spot,
                'margin' => $margin,
                'futures' => $futures,
                'collateral_factor' => $factor,
                'collateral_value_usdt' => $collateralValue,
            ];
        }

        $borrowed = '0';
        foreach (MarginLoan::query()->where('user_id', $userId)->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID, MarginLoan::STATUS_LIQUIDATING])->get() as $loan) {
            $borrowed = FinancialDecimal::add($borrowed, $this->valueInUsdt((string) $loan->asset, FinancialDecimal::add((string) $loan->principal, (string) $loan->accrued_interest)));
        }

        $collateral = '0';
        foreach ($assets as $asset) {
            $collateral = FinancialDecimal::add($collateral, (string) $asset['collateral_value_usdt']);
        }

        $positionsNotional = '0';
        foreach (FuturesPosition::query()->where('user_id', $userId)->where('status', 'open')->get() as $position) {
            $positionsNotional = FinancialDecimal::add($positionsNotional, (string) ($position->notional_value ?? '0'));
        }

        $net = FinancialDecimal::sub($collateral, $borrowed);
        $marginRatio = FinancialDecimal::compare($borrowed, '0') > 0 ? FinancialDecimal::div($collateral, $borrowed) : null;

        TradingExposureSnapshot::query()->create([
            'snapshot_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'product' => 'aggregate',
            'gross_exposure' => FinancialDecimal::add($collateral, $positionsNotional),
            'net_exposure' => $net,
            'borrowed_amount' => $borrowed,
            'reserved_amount' => '0',
            'metadata' => ['assets' => array_values($assets), 'futures_notional' => $positionsNotional],
            'calculated_at' => now(),
        ]);

        return [
            'user_id' => $userId,
            'assets' => array_values($assets),
            'collateral_value_usdt' => $collateral,
            'borrowed_value_usdt' => $borrowed,
            'futures_notional_usdt' => $positionsNotional,
            'net_equity_usdt' => $net,
            'margin_ratio' => $marginRatio,
            'status' => FinancialDecimal::compare($net, '0') < 0 ? 'NEGATIVE_EQUITY' : 'OK',
        ];
    }

    public function collateralFactor(string $asset): string
    {
        $config = CollateralConfiguration::query()->where('asset', strtoupper($asset))->where('status', 'ACTIVE')->first();
        if ($config) {
            return (string) $config->collateral_factor;
        }

        return match (strtoupper($asset)) {
            'USDT', 'USDC', 'USD' => '0.95000000',
            'BTC', 'ETH' => '0.80000000',
            default => '0.00000000',
        };
    }

    private function valueInUsdt(string $asset, string $amount): string
    {
        $asset = strtoupper($asset);
        if (in_array($asset, ['USDT', 'USDC', 'USD'], true)) {
            return $amount;
        }

        $price = (string) config('margin.reference_prices.' . $asset, $asset === 'BTC' ? '50000' : ($asset === 'ETH' ? '3000' : '0'));
        return FinancialDecimal::mul($amount, $price);
    }
}
