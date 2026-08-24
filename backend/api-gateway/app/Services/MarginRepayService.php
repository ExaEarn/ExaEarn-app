<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAssetConfig;
use App\Models\MarginLoan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarginRepayService
{
    public function __construct(
        private readonly MarginAccountService $accounts,
        private readonly MarginInterestAccrualService $accruals,
        private readonly MarginLiquidityService $liquidity,
        private readonly SettlementService $settlement,
        private readonly MarginRealtimeService $realtime,
    ) {
    }

    public function repay(MarginLoan $loan, string $amount, string $idempotencyKey): MarginLoan
    {
        return DB::transaction(function () use ($amount, $idempotencyKey, $loan): MarginLoan {
            $reference = 'margin-repay:' . $idempotencyKey;
            $loan = MarginLoan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if (in_array($loan->status, [MarginLoan::STATUS_REPAID, MarginLoan::STATUS_DEFAULTED], true)) {
                return $loan;
            }
            if (($loan->metadata['last_repay_idempotency_key'] ?? null) === $idempotencyKey) {
                return $loan;
            }

            $this->accruals->accrueLoan($loan);
            $loan->refresh();

            $payment = FinancialDecimal::normalize($amount);
            $accruedInterest = (string) $loan->accrued_interest;
            $totalDebt = FinancialDecimal::add((string) $loan->principal, $accruedInterest);
            if (FinancialDecimal::compare($payment, $totalDebt) > 0) {
                $payment = $totalDebt;
            }
            if (FinancialDecimal::compare($payment, '0') <= 0) {
                throw new RuntimeException('Repayment amount must be greater than zero.');
            }

            $interestPaid = FinancialDecimal::min($payment, $accruedInterest);
            $principalPaid = FinancialDecimal::sub($payment, $interestPaid);
            $config = MarginAssetConfig::query()->where('asset', $loan->asset)->first();
            $reserveFactor = FinancialDecimal::max('0', FinancialDecimal::min('1', (string) ($config?->reserve_factor ?? '0')));
            $reserveShare = FinancialDecimal::mul($interestPaid, $reserveFactor);

            $this->settlement->marginRepay(
                (int) $loan->user_id,
                $this->accounts->ledgerAccountType($loan->marginAccount),
                (string) $loan->asset,
                $principalPaid,
                $interestPaid,
                $reserveFactor,
                $reference,
                ['loan_id' => $loan->id, 'idempotency_key' => $idempotencyKey],
            );

            $loan->accrued_interest = FinancialDecimal::sub((string) $loan->accrued_interest, $interestPaid);
            if (FinancialDecimal::compare(FinancialDecimal::abs((string) $loan->accrued_interest), '0.00000001') <= 0) {
                $loan->accrued_interest = '0';
            }
            $loan->principal = FinancialDecimal::sub((string) $loan->principal, $principalPaid);
            $loan->status = FinancialDecimal::compare((string) $loan->principal, '0') === 0 && FinancialDecimal::compare((string) $loan->accrued_interest, '0') === 0
                ? MarginLoan::STATUS_REPAID
                : MarginLoan::STATUS_PARTIALLY_REPAID;
            $loan->metadata = array_merge($loan->metadata ?? [], [
                'last_repay_idempotency_key' => $idempotencyKey,
                'last_repay_reference' => $reference,
            ]);
            $loan->save();

            $this->liquidity->restoreLiquidity((string) $loan->asset, $principalPaid, $reserveShare);

            $this->realtime->publishAccount($loan->marginAccount, 'margin.loan.repaid', [
                'loan_uuid' => $loan->loan_uuid,
                'asset' => $loan->asset,
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
                'status' => $loan->status,
            ]);

            return $loan->fresh();
        });
    }
}
