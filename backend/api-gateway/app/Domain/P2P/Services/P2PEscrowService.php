<?php

declare(strict_types=1);

namespace App\Domain\P2P\Services;

use App\Models\LedgerTransaction;
use App\Models\P2PTrade;
use App\Models\Reservation;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\ReservationService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class P2PEscrowService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlements,
        private readonly P2PFeeService $fees,
    ) {
    }

    public function reserveForTrade(P2PTrade $trade, int $sellerId, string $asset, string $amount): Reservation
    {
        return DB::transaction(function () use ($amount, $asset, $sellerId, $trade): Reservation {
            if ($trade->escrow_reservation_id) {
                return Reservation::query()->where('reservation_id', $trade->escrow_reservation_id)->lockForUpdate()->firstOrFail();
            }

            $sellerFunding = $this->ledger->getOrCreateAccount($sellerId, 'funding', $asset);
            $reservation = $this->reservations->reserve(
                $sellerFunding->id,
                $asset,
                $amount,
                'p2p_escrow',
                'p2p_trade',
                (string) $trade->trade_uuid,
                'p2p:escrow:' . $trade->trade_uuid,
                [
                    'trade_id' => $trade->id,
                    'trade_uuid' => $trade->trade_uuid,
                    'buyer_id' => $trade->buyer_id,
                    'seller_id' => $sellerId,
                ],
                $trade->payment_deadline,
            );

            DB::table('p2p_escrows')->updateOrInsert(
                ['trade_id' => $trade->id],
                [
                    'escrow_id' => (string) Str::uuid(),
                    'reservation_id' => $reservation->reservation_id,
                    'asset' => strtoupper($asset),
                    'amount' => FinancialDecimal::normalize($amount),
                    'status' => 'reserved',
                    'metadata' => json_encode(['source' => 'canonical_reservation'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $trade->escrow_reservation_id = (string) $reservation->reservation_id;
            $trade->escrow_ledger_reference = 'p2p:escrow:' . $trade->trade_uuid;
            $trade->save();

            return $reservation;
        });
    }

    public function releaseToBuyer(P2PTrade $trade, int $actorUserId): LedgerTransaction
    {
        return DB::transaction(function () use ($actorUserId, $trade): LedgerTransaction {
            /** @var P2PTrade $trade */
            $trade = P2PTrade::query()->lockForUpdate()->findOrFail($trade->id);
            if ($trade->status === 'released' && $trade->release_ledger_transaction_id) {
                return LedgerTransaction::query()->findOrFail($trade->release_ledger_transaction_id);
            }
            if (!in_array($trade->status, ['payment_sent', 'disputed'], true)) {
                throw new RuntimeException('Trade is not ready for escrow release.');
            }
            if (!$trade->escrow_reservation_id) {
                throw new RuntimeException('Trade does not have a canonical escrow reservation.');
            }

            $fee = $this->fees->quote((string) $trade->asset, (string) $trade->crypto_amount);
            $reference = 'p2p:release:' . $trade->trade_uuid;
            $tx = $this->settlements->p2pEscrowRelease(
                (string) $trade->escrow_reservation_id,
                (int) $trade->buyer_id,
                (string) $fee['net_amount'],
                (string) $fee['fee_amount'],
                $reference,
                [
                    'trade_id' => $trade->id,
                    'trade_uuid' => $trade->trade_uuid,
                    'seller_id' => $trade->seller_id,
                    'buyer_id' => $trade->buyer_id,
                    'actor_user_id' => $actorUserId,
                    'fee_rate' => $fee['fee_rate'],
                ],
            );

            DB::table('p2p_escrows')->where('trade_id', $trade->id)->update([
                'status' => 'released',
                'release_reference' => $reference,
                'released_at' => now(),
                'updated_at' => now(),
            ]);

            return $tx;
        });
    }

    public function returnToSeller(P2PTrade $trade, int $actorUserId, string $reason): string
    {
        return DB::transaction(function () use ($actorUserId, $reason, $trade): string {
            /** @var P2PTrade $trade */
            $trade = P2PTrade::query()->lockForUpdate()->findOrFail($trade->id);
            if ($trade->status === 'cancelled' && $trade->return_ledger_reference) {
                return (string) $trade->return_ledger_reference;
            }
            if ($trade->status === 'released') {
                throw new RuntimeException('Released escrow cannot be returned to seller.');
            }
            if (!$trade->escrow_reservation_id) {
                throw new RuntimeException('Trade does not have a canonical escrow reservation.');
            }

            $reference = 'p2p:return:' . $trade->trade_uuid;
            $this->reservations->release((string) $trade->escrow_reservation_id, null, [
                'return_reference' => $reference,
                'reason' => $reason,
                'actor_user_id' => $actorUserId,
            ]);

            DB::table('p2p_escrows')->where('trade_id', $trade->id)->update([
                'status' => 'returned',
                'return_reference' => $reference,
                'returned_at' => now(),
                'updated_at' => now(),
            ]);

            return $reference;
        });
    }
}
