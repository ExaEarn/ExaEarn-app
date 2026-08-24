<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAccount;
use App\Models\MarginAssetConfig;
use App\Models\MarginLoan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MarginBorrowService
{
    public function __construct(
        private readonly MarginAccountService $accounts,
        private readonly MarginHealthService $health,
        private readonly MarginLiquidityService $liquidity,
        private readonly MarginInterestRateService $rates,
        private readonly SettlementService $settlement,
        private readonly MarginRealtimeService $realtime,
    ) {
    }

    public function borrow(MarginAccount $account, string $asset, string $amount, string $idempotencyKey): MarginLoan
    {
        return DB::transaction(function () use ($account, $amount, $asset, $idempotencyKey): MarginLoan {
            $existing = MarginLoan::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $account = MarginAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->accounts->assertActive($account);
            $asset = strtoupper($asset);
            $amount = FinancialDecimal::normalize($amount);
            $config = MarginAssetConfig::query()->where('asset', $asset)->lockForUpdate()->first();
            if (!$config || !$config->borrow_enabled || $config->status !== 'ENABLED') {
                throw new RuntimeException('Asset is not enabled for margin borrowing.');
            }
            if (FinancialDecimal::compare($amount, (string) $config->minimum_borrow) < 0) {
                throw new RuntimeException('Borrow amount is below the minimum.');
            }
            if (FinancialDecimal::compare((string) $config->maximum_borrow, '0') > 0 && FinancialDecimal::compare($amount, (string) $config->maximum_borrow) > 0) {
                throw new RuntimeException('Borrow amount exceeds the maximum.');
            }

            $pool = $this->liquidity->pool($asset);
            $rate = $this->rates->annualRate($config, $pool);
            $this->health->assertProjectedBorrowAllowed($account, $asset, $amount);
            $this->liquidity->consumeLiquidity($asset, $amount);

            $reference = 'margin-borrow:' . $idempotencyKey;
            $this->settlement->marginBorrow(
                (int) $account->user_id,
                $this->accounts->ledgerAccountType($account),
                $asset,
                $amount,
                $reference,
                ['margin_account_id' => $account->id, 'idempotency_key' => $idempotencyKey],
            );

            $loan = MarginLoan::query()->create([
                'loan_uuid' => (string) Str::uuid(),
                'margin_account_id' => $account->id,
                'user_id' => $account->user_id,
                'asset' => $asset,
                'principal' => $amount,
                'accrued_interest' => '0',
                'interest_rate' => $rate,
                'opened_at' => now(),
                'last_accrual_at' => now(),
                'status' => MarginLoan::STATUS_ACTIVE,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['ledger_reference' => $reference],
            ]);

            $this->realtime->publishAccount($account, 'margin.loan.opened', [
                'loan_uuid' => $loan->loan_uuid,
                'asset' => $asset,
                'principal' => $amount,
                'interest_rate' => $rate,
            ]);

            return $loan;
        });
    }
}
