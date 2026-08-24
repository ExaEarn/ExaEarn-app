<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;

class FiatWithdrawalReserveService
{
    public function refresh(string $currency, ?string $provider = null): array
    {
        $currency = strtoupper($currency);
        $pending = (string) DB::table('phase10_fiat_withdrawals')
            ->where('currency', $currency)
            ->whereIn('status', ['REQUESTED', 'RESERVED', 'SUBMITTED', 'PROCESSING', 'UNKNOWN'])
            ->sum('amount');
        $volume24h = (string) DB::table('phase10_fiat_withdrawals')
            ->where('currency', $currency)
            ->where('created_at', '>=', now()->subDay())
            ->whereNotIn('status', ['FAILED', 'CANCELLED', 'REVERSED'])
            ->sum('amount');
        $providerBalance = app(PaymentProviderRouter::class)->provider($provider ?? 'sandbox')->getBalance($currency);
        $minimum = FinancialDecimal::max($pending, FinancialDecimal::mul($volume24h, '0.25'));
        $target = FinancialDecimal::add($pending, FinancialDecimal::mul($volume24h, '0.50'));
        $stress = FinancialDecimal::add($target, FinancialDecimal::mul($volume24h, '0.25'));
        $status = FinancialDecimal::compare($providerBalance, $minimum) >= 0 ? 'FUNDED' : 'LOW';

        DB::table('fiat_withdrawal_reserves')->updateOrInsert(
            ['currency' => $currency],
            [
                'pending_withdrawals' => $pending,
                'volume_24h' => $volume24h,
                'provider_balance' => $providerBalance,
                'minimum_reserve' => $minimum,
                'target_reserve' => $target,
                'stress_reserve' => $stress,
                'status' => $status,
                'metadata' => json_encode(['provider' => $provider ?? 'sandbox'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (array) DB::table('fiat_withdrawal_reserves')->where('currency', $currency)->first();
    }
}
