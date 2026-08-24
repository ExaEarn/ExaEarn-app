<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginInterestAccrual;
use App\Models\MarginLoan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarginInterestAccrualService
{
    public function accrueLoan(MarginLoan $loan, ?CarbonInterface $periodEnd = null): ?MarginInterestAccrual
    {
        $periodEnd = ($periodEnd ?: now())->copy()->startOfSecond();

        return DB::transaction(function () use ($loan, $periodEnd): ?MarginInterestAccrual {
            $loan = MarginLoan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if (!in_array($loan->status, [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID], true)) {
                return null;
            }

            $periodStart = $loan->last_accrual_at;
            if ($periodEnd->lessThanOrEqualTo($periodStart)) {
                return null;
            }

            $existing = MarginInterestAccrual::query()
                ->where('margin_loan_id', $loan->id)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->first();
            if ($existing) {
                return $existing;
            }

            $seconds = (string) (int) floor($periodStart->diffInSeconds($periodEnd));
            if ((int) $seconds <= 0) {
                return null;
            }
            if ((int) $seconds < (int) config('margin.minimum_accrual_seconds', 60)) {
                return null;
            }
            $yearFraction = FinancialDecimal::div($seconds, (string) config('margin.seconds_per_year'));
            $interest = FinancialDecimal::mul(FinancialDecimal::mul((string) $loan->principal, (string) $loan->interest_rate), $yearFraction);

            $accrual = MarginInterestAccrual::query()->create([
                'accrual_id' => (string) Str::uuid(),
                'margin_loan_id' => $loan->id,
                'asset' => $loan->asset,
                'principal_basis' => $loan->principal,
                'interest_rate' => $loan->interest_rate,
                'interest_amount' => $interest,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'metadata' => ['seconds' => $seconds],
            ]);

            $loan->accrued_interest = FinancialDecimal::add((string) $loan->accrued_interest, $interest);
            $loan->last_accrual_at = $periodEnd;
            $loan->save();

            return $accrual;
        });
    }
}
