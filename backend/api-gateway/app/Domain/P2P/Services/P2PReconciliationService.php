<?php

declare(strict_types=1);

namespace App\Domain\P2P\Services;

use App\Models\P2PTrade;
use App\Models\Reservation;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class P2PReconciliationService
{
    public function run(): array
    {
        $findings = [];
        $activeEscrowTotal = '0';

        P2PTrade::query()
            ->whereIn('status', ['pending', 'payment_sent', 'disputed'])
            ->orderBy('id')
            ->chunkById(100, function ($trades) use (&$activeEscrowTotal, &$findings): void {
                foreach ($trades as $trade) {
                    if (!$trade->escrow_reservation_id) {
                        $findings[] = [
                            'type' => 'missing_reservation',
                            'trade_uuid' => $trade->trade_uuid,
                        ];
                        continue;
                    }

                    $reservation = Reservation::query()->where('reservation_id', $trade->escrow_reservation_id)->first();
                    if (!$reservation) {
                        $findings[] = [
                            'type' => 'reservation_not_found',
                            'trade_uuid' => $trade->trade_uuid,
                            'reservation_id' => $trade->escrow_reservation_id,
                        ];
                        continue;
                    }

                    if (!in_array($reservation->status, [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED], true)) {
                        $findings[] = [
                            'type' => 'inactive_reservation_for_open_trade',
                            'trade_uuid' => $trade->trade_uuid,
                            'reservation_status' => $reservation->status,
                        ];
                    }

                    if (FinancialDecimal::compare((string) $reservation->remaining_amount, (string) $trade->crypto_amount) < 0) {
                        $findings[] = [
                            'type' => 'reservation_less_than_trade_amount',
                            'trade_uuid' => $trade->trade_uuid,
                            'remaining_amount' => (string) $reservation->remaining_amount,
                            'trade_amount' => (string) $trade->crypto_amount,
                        ];
                    }

                    $activeEscrowTotal = FinancialDecimal::add($activeEscrowTotal, (string) $reservation->remaining_amount);
                }
            });

        $runId = (string) Str::uuid();
        DB::table('p2p_reconciliation_runs')->insert([
            'run_id' => $runId,
            'status' => $findings === [] ? 'completed' : 'differences_found',
            'active_escrow_total' => $activeEscrowTotal,
            'difference_count' => count($findings),
            'findings' => json_encode($findings, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'run_id' => $runId,
            'status' => $findings === [] ? 'PASS' : 'FAIL',
            'active_escrow_total' => $activeEscrowTotal,
            'difference_count' => count($findings),
            'findings' => $findings,
        ];
    }
}
