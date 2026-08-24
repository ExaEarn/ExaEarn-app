<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReservationService
{
    public function __construct(private readonly BalanceProjectionService $projections)
    {
    }

    public function reserve(
        int $accountId,
        string $asset,
        string $amount,
        string $purpose,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
        mixed $expiresAt = null,
    ): Reservation {
        return DB::transaction(function () use ($accountId, $amount, $asset, $expiresAt, $idempotencyKey, $metadata, $purpose, $referenceId, $referenceType): Reservation {
            if ($idempotencyKey) {
                $existing = Reservation::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
            }

            $account = Account::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();
            $asset = strtoupper($asset);
            if (strtoupper((string) $account->asset) !== $asset) {
                throw new RuntimeException('Reservation asset mismatch.');
            }

            $normalizedAmount = FinancialDecimal::normalize($amount);
            if (FinancialDecimal::compare($normalizedAmount, '0') <= 0) {
                throw new RuntimeException('Reservation amount must be greater than zero.');
            }

            $available = $this->projections->accountProjection($account)['available'];
            if (FinancialDecimal::compare($available, $normalizedAmount) < 0) {
                throw new RuntimeException('Insufficient available balance for reservation.');
            }

            return Reservation::query()->create([
                'reservation_id' => (string) Str::uuid(),
                'account_id' => $account->id,
                'user_id' => $account->user_id,
                'asset' => $asset,
                'amount' => $normalizedAmount,
                'remaining_amount' => $normalizedAmount,
                'purpose' => $purpose,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'status' => Reservation::STATUS_ACTIVE,
                'metadata' => $metadata,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function reserveUserAccount(
        int $userId,
        string $accountType,
        string $asset,
        string $amount,
        string $purpose,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): Reservation {
        $account = app(LedgerService::class)->getOrCreateAccount($userId, $accountType, $asset);

        return $this->reserve($account->id, $asset, $amount, $purpose, $referenceType, $referenceId, $idempotencyKey, $metadata);
    }

    public function release(string $reservationId, ?string $amount = null, array $metadata = []): Reservation
    {
        return DB::transaction(function () use ($amount, $metadata, $reservationId): Reservation {
            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $this->assertMutable($reservation);

            $release = $amount === null ? (string) $reservation->remaining_amount : FinancialDecimal::normalize($amount);
            if (FinancialDecimal::compare($release, (string) $reservation->remaining_amount) > 0) {
                throw new RuntimeException('Cannot release more than remaining reservation amount.');
            }

            $reservation->remaining_amount = FinancialDecimal::sub((string) $reservation->remaining_amount, $release);
            $reservation->metadata = array_merge($reservation->metadata ?? [], $metadata, ['last_release_amount' => $release]);
            if (FinancialDecimal::compare((string) $reservation->remaining_amount, '0') === 0) {
                $reservation->status = Reservation::STATUS_RELEASED;
                $reservation->released_at = now();
            } else {
                $reservation->status = Reservation::STATUS_PARTIALLY_CONSUMED;
            }
            $reservation->save();

            return $reservation->fresh();
        });
    }

    public function consume(string $reservationId, string $amount, array $metadata = []): Reservation
    {
        return DB::transaction(function () use ($amount, $metadata, $reservationId): Reservation {
            $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            $this->assertMutable($reservation);

            $consume = FinancialDecimal::normalize($amount);
            if (FinancialDecimal::compare($consume, (string) $reservation->remaining_amount) > 0) {
                throw new RuntimeException('Cannot consume more than remaining reservation amount.');
            }

            $reservation->remaining_amount = FinancialDecimal::sub((string) $reservation->remaining_amount, $consume);
            $reservation->metadata = array_merge($reservation->metadata ?? [], $metadata, ['last_consume_amount' => $consume]);
            if (FinancialDecimal::compare((string) $reservation->remaining_amount, '0') === 0) {
                $reservation->status = Reservation::STATUS_CONSUMED;
                $reservation->consumed_at = now();
            } else {
                $reservation->status = Reservation::STATUS_PARTIALLY_CONSUMED;
            }
            $reservation->save();

            return $reservation->fresh();
        });
    }

    public function expireDueReservations(): int
    {
        $count = 0;
        Reservation::query()
            ->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $reservation) {
                    $reservation->status = Reservation::STATUS_EXPIRED;
                    $reservation->released_at = now();
                    $reservation->save();
                    $count++;
                }
            });

        return $count;
    }

    private function assertMutable(Reservation $reservation): void
    {
        if (!in_array($reservation->status, [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED], true)) {
            throw new RuntimeException('Reservation is no longer active.');
        }
    }
}
