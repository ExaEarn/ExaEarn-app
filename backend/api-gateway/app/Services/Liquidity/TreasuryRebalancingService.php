<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\TreasuryRebalancingRun;
use App\Services\FinancialDecimal;
use Illuminate\Support\Str;

class TreasuryRebalancingService
{
    public function evaluate(string $asset): TreasuryRebalancingRun
    {
        $asset = strtoupper($asset);
        $reserve = app(WithdrawalLiquidityReserveService::class)->calculate($asset);
        $actions = [];
        $status = 'NO_ACTION';

        if (FinancialDecimal::compare((string) $reserve->available_operational_liquidity, (string) $reserve->minimum_reserve) < 0) {
            $deficit = FinancialDecimal::sub((string) $reserve->target_reserve, (string) $reserve->available_operational_liquidity);
            $actions[] = [
                'mode' => 'TREASURY_TO_HOT',
                'asset' => $asset,
                'amount' => FinancialDecimal::max('0', $deficit),
                'reason' => 'withdrawal_reserve_below_minimum',
                'requires_approval' => true,
            ];
            $status = 'ACTION_REQUIRED';
        }

        return TreasuryRebalancingRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'asset' => $asset,
            'status' => $status,
            'actions' => $actions,
            'metadata' => ['policy' => 'phase8-threshold-rebalancing'],
            'evaluated_at' => now(),
        ]);
    }
}
