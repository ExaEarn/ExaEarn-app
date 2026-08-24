<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiatTransactionLimitService
{
    public function assertWithdrawalAllowed(int $userId, string $currency, string $amount): void
    {
        $currency = strtoupper($currency);
        $registry = app(FiatCurrencyRegistry::class)->currency($currency);
        if (! (bool) $registry['withdrawal_enabled']) {
            throw new RuntimeException('Fiat withdrawals are not enabled for this currency.');
        }

        if (FinancialDecimal::compare($amount, (string) $registry['minimum_withdrawal']) < 0) {
            throw new RuntimeException('Withdrawal amount is below the configured minimum.');
        }

        if (FinancialDecimal::compare((string) $registry['maximum_withdrawal'], '0') > 0
            && FinancialDecimal::compare($amount, (string) $registry['maximum_withdrawal']) > 0) {
            throw new RuntimeException('Withdrawal amount is above the configured maximum.');
        }

        $volumeToday = (string) DB::table('phase10_fiat_withdrawals')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->whereDate('created_at', now()->toDateString())
            ->whereNotIn('status', ['FAILED', 'CANCELLED', 'REVERSED'])
            ->sum('amount');

        $dailyLimit = (string) $registry['daily_limit'];
        if (FinancialDecimal::compare($dailyLimit, '0') > 0
            && FinancialDecimal::compare(FinancialDecimal::add($volumeToday, $amount), $dailyLimit) > 0) {
            throw new RuntimeException('Withdrawal exceeds the remaining daily limit.');
        }
    }
}
