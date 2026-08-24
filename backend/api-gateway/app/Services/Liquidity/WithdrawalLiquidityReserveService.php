<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\Withdrawal;
use App\Models\WithdrawalLiquidityReserve;
use App\Services\FinancialDecimal;
use Illuminate\Support\Str;

class WithdrawalLiquidityReserveService
{
    public function calculate(string $asset): WithdrawalLiquidityReserve
    {
        $asset = strtoupper($asset);
        $pending = $this->pendingWithdrawals($asset);
        $minimum = FinancialDecimal::max((string) config('liquidity.treasury.default_minimum_reserve', '0'), $pending);
        $target = FinancialDecimal::mul($minimum, (string) config('liquidity.treasury.default_target_multiplier', '1.50'));
        $stress = FinancialDecimal::mul($minimum, (string) config('liquidity.treasury.default_stress_multiplier', '3.00'));
        $available = app(TreasuryInventoryService::class)->availableForBucket($asset, 'WITHDRAWAL_RESERVE');
        $status = FinancialDecimal::compare($available, $minimum) < 0
            ? 'BELOW_MINIMUM'
            : (FinancialDecimal::compare($available, $target) < 0 ? 'BELOW_TARGET' : 'FUNDED');

        return WithdrawalLiquidityReserve::query()->updateOrCreate(
            ['asset' => $asset],
            [
                'reserve_id' => (string) (WithdrawalLiquidityReserve::query()->where('asset', $asset)->value('reserve_id') ?: Str::uuid()),
                'minimum_reserve' => $minimum,
                'target_reserve' => $target,
                'stress_reserve' => $stress,
                'pending_withdrawals' => $pending,
                'available_operational_liquidity' => $available,
                'formula_version' => (string) config('liquidity.treasury.reserve_formula_version', 'phase8-withdrawal-reserve-v1'),
                'status' => $status,
                'metadata' => ['source' => 'phase8'],
                'calculated_at' => now(),
            ]
        );
    }

    public function assertProtected(string $asset, string $amount): void
    {
        $reserve = $this->calculate($asset);
        $after = FinancialDecimal::sub((string) $reserve->available_operational_liquidity, FinancialDecimal::normalize($amount));
        if (FinancialDecimal::compare($after, (string) $reserve->minimum_reserve) < 0) {
            throw new \RuntimeException('WITHDRAWAL_RESERVE_BREACH');
        }
    }

    private function pendingWithdrawals(string $asset): string
    {
        if (! class_exists(Withdrawal::class)) {
            return '0';
        }

        return FinancialDecimal::normalize((string) Withdrawal::query()
            ->where('currency', $asset)
            ->whereIn('status', ['pending', 'processing', 'awaiting_verification', 'submitted'])
            ->sum('amount'));
    }
}
