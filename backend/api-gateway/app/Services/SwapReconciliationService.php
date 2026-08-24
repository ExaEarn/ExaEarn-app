<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerTransaction;
use App\Models\Reservation;
use App\Models\Swap;

class SwapReconciliationService
{
    public function report(): array
    {
        $findings = [];

        Swap::query()->latest()->limit(500)->get()->each(function (Swap $swap) use (&$findings): void {
            $reservationId = (string) data_get($swap->metadata, 'reservation_id', '');
            $settlementReference = (string) data_get($swap->metadata, 'settlement_reference', 'convert:' . $swap->swap_id);
            $reservation = $reservationId !== '' ? Reservation::query()->where('reservation_id', $reservationId)->first() : null;
            $settlement = LedgerTransaction::query()->where('reference', $settlementReference)->first();

            if ($swap->status === 'completed' && !$settlement) {
                $findings[] = [
                    'severity' => 'critical',
                    'swap_id' => $swap->swap_id,
                    'issue' => 'completed_swap_missing_settlement',
                    'reference' => $settlementReference,
                ];
            }

            if ($swap->status === 'completed' && $reservation && $reservation->status !== Reservation::STATUS_CONSUMED) {
                $findings[] = [
                    'severity' => 'critical',
                    'swap_id' => $swap->swap_id,
                    'issue' => 'completed_swap_reservation_not_consumed',
                    'reservation_id' => $reservationId,
                    'reservation_status' => $reservation->status,
                ];
            }

            if ($swap->status === 'failed' && $reservation && in_array($reservation->status, [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED], true)) {
                $findings[] = [
                    'severity' => 'high',
                    'swap_id' => $swap->swap_id,
                    'issue' => 'failed_swap_reservation_still_active',
                    'reservation_id' => $reservationId,
                ];
            }

            if ($swap->idempotency_key && Swap::query()->where('user_id', $swap->user_id)->where('idempotency_key', $swap->idempotency_key)->count() > 1) {
                $findings[] = [
                    'severity' => 'critical',
                    'swap_id' => $swap->swap_id,
                    'issue' => 'duplicate_swap_idempotency_key',
                    'idempotency_key' => $swap->idempotency_key,
                ];
            }
        });

        return [
            'checked_swaps' => Swap::query()->count(),
            'findings' => $findings,
            'status' => $findings === [] ? 'PASS' : 'FAIL',
        ];
    }
}
