<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\ExternalVenueBalance;
use App\Models\LiquidityReconciliationDifference;
use App\Models\LiquidityReconciliationRun;
use App\Models\LiquidityReservation;
use App\Models\TreasuryLiquidityBucket;
use App\Models\WithdrawalLiquidityReserve;
use App\Services\FinancialDecimal;
use Illuminate\Support\Str;

class LiquidityReconciliationService
{
    public function run(): LiquidityReconciliationRun
    {
        $started = now();
        $differences = [];

        foreach (ExternalVenueBalance::query()->get() as $balance) {
            if (FinancialDecimal::compare((string) $balance->reserved_for_routing, (string) $balance->available) > 0) {
                $differences[] = ['scope' => 'external_venue', 'severity' => 'CRITICAL', 'code' => 'VENUE_RESERVED_EXCEEDS_AVAILABLE', 'message' => 'External venue routing reservations exceed available venue balance.', 'metadata' => ['balance_id' => $balance->id]];
            }
        }

        foreach (TreasuryLiquidityBucket::query()->get() as $bucket) {
            if (FinancialDecimal::compare((string) $bucket->reserved_amount, (string) $bucket->allocated_amount) > 0) {
                $differences[] = ['scope' => 'treasury', 'severity' => 'CRITICAL', 'code' => 'BUCKET_RESERVED_EXCEEDS_ALLOCATED', 'message' => 'Treasury bucket reservation exceeds allocation.', 'metadata' => ['bucket_id' => $bucket->id]];
            }
        }

        foreach (WithdrawalLiquidityReserve::query()->where('status', 'BELOW_MINIMUM')->get() as $reserve) {
            $differences[] = ['scope' => 'withdrawal_reserve', 'severity' => 'CRITICAL', 'code' => 'WITHDRAWAL_RESERVE_BELOW_MINIMUM', 'message' => 'Withdrawal reserve is below minimum policy.', 'metadata' => ['asset' => $reserve->asset]];
        }

        foreach (LiquidityReservation::query()->whereIn('status', ['ACTIVE', 'PARTIALLY_CONSUMED'])->get() as $reservation) {
            if (FinancialDecimal::compare((string) $reservation->remaining_amount, '0') < 0) {
                $differences[] = ['scope' => 'liquidity_reservation', 'severity' => 'CRITICAL', 'code' => 'NEGATIVE_RESERVATION', 'message' => 'Liquidity reservation has negative remaining amount.', 'metadata' => ['reservation_id' => $reservation->reservation_id]];
            }
        }

        $run = LiquidityReconciliationRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'status' => $differences === [] ? 'PASS' : 'FAIL',
            'differences_count' => count($differences),
            'summary' => ['differences' => count($differences)],
            'started_at' => $started,
            'finished_at' => now(),
        ]);

        foreach ($differences as $difference) {
            LiquidityReconciliationDifference::query()->create(array_merge($difference, [
                'liquidity_reconciliation_run_id' => $run->id,
            ]));
        }

        return $run->fresh();
    }
}
