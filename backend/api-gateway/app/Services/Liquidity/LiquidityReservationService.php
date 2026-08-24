<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\ExternalVenueBalance;
use App\Models\LiquidityReservation;
use App\Models\TreasuryLiquidityBucket;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LiquidityReservationService
{
    public function reserve(string $scope, string $sourceCode, string $asset, string $amount, string $purpose, string $referenceType, string $referenceId, string $idempotencyKey, array $metadata = []): LiquidityReservation
    {
        return DB::transaction(function () use ($scope, $sourceCode, $asset, $amount, $purpose, $referenceType, $referenceId, $idempotencyKey, $metadata): LiquidityReservation {
            $existing = LiquidityReservation::query()
                ->where('source_code', strtoupper($sourceCode))
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $normalized = FinancialDecimal::normalize($amount);
            if (FinancialDecimal::compare($normalized, '0') <= 0) {
                throw new RuntimeException('Liquidity reservation amount must be positive.');
            }

            $this->assertAvailable($scope, strtoupper($sourceCode), strtoupper($asset), $normalized);

            $reservation = LiquidityReservation::query()->create([
                'reservation_id' => (string) Str::uuid(),
                'scope' => strtoupper($scope),
                'source_code' => strtoupper($sourceCode),
                'asset' => strtoupper($asset),
                'amount' => $normalized,
                'remaining_amount' => $normalized,
                'purpose' => strtoupper($purpose),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'status' => 'ACTIVE',
                'metadata' => $metadata,
            ]);

            $this->applyReserve($scope, strtoupper($sourceCode), strtoupper($asset), $normalized);

            return $reservation->fresh();
        });
    }

    public function consume(string $reservationId, string $amount): LiquidityReservation
    {
        return DB::transaction(function () use ($reservationId, $amount): LiquidityReservation {
            $reservation = LiquidityReservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            if (! in_array($reservation->status, ['ACTIVE', 'PARTIALLY_CONSUMED'], true)) {
                throw new RuntimeException('Liquidity reservation is not consumable.');
            }

            $consume = FinancialDecimal::normalize($amount);
            if (FinancialDecimal::compare($consume, (string) $reservation->remaining_amount) > 0) {
                throw new RuntimeException('Cannot consume more than remaining liquidity reservation.');
            }

            $reservation->remaining_amount = FinancialDecimal::sub((string) $reservation->remaining_amount, $consume);
            $reservation->status = FinancialDecimal::compare((string) $reservation->remaining_amount, '0') <= 0 ? 'CONSUMED' : 'PARTIALLY_CONSUMED';
            $reservation->save();

            return $reservation->fresh();
        });
    }

    public function release(string $reservationId): LiquidityReservation
    {
        return DB::transaction(function () use ($reservationId): LiquidityReservation {
            $reservation = LiquidityReservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            if (! in_array($reservation->status, ['ACTIVE', 'PARTIALLY_CONSUMED'], true)) {
                return $reservation;
            }

            $remaining = (string) $reservation->remaining_amount;
            if (FinancialDecimal::compare($remaining, '0') > 0) {
                $this->applyRelease((string) $reservation->scope, (string) $reservation->source_code, (string) $reservation->asset, $remaining);
            }

            $reservation->remaining_amount = '0';
            $reservation->status = 'RELEASED';
            $reservation->save();

            return $reservation->fresh();
        });
    }

    private function assertAvailable(string $scope, string $sourceCode, string $asset, string $amount): void
    {
        if (strtoupper($scope) === 'EXTERNAL_VENUE') {
            $balance = ExternalVenueBalance::query()
                ->whereHas('account', fn ($query) => $query->where('venue', $sourceCode))
                ->where('asset', $asset)
                ->lockForUpdate()
                ->first();
            $available = $balance
                ? FinancialDecimal::sub((string) $balance->available, FinancialDecimal::add((string) $balance->reserved_for_routing, (string) $balance->operational_minimum))
                : '0';
            if (FinancialDecimal::compare($available, $amount) < 0) {
                throw new RuntimeException('EXTERNAL_VENUE_LIQUIDITY_INSUFFICIENT');
            }
            return;
        }

        $bucket = TreasuryLiquidityBucket::query()
            ->where('asset', $asset)
            ->where('bucket', $sourceCode)
            ->lockForUpdate()
            ->first();
        $available = $bucket ? FinancialDecimal::sub((string) $bucket->allocated_amount, (string) $bucket->reserved_amount) : '0';
        if (FinancialDecimal::compare($available, $amount) < 0) {
            throw new RuntimeException('TREASURY_BUCKET_LIQUIDITY_INSUFFICIENT');
        }
    }

    private function applyReserve(string $scope, string $sourceCode, string $asset, string $amount): void
    {
        if (strtoupper($scope) === 'EXTERNAL_VENUE') {
            $balance = ExternalVenueBalance::query()
                ->whereHas('account', fn ($query) => $query->where('venue', $sourceCode))
                ->where('asset', $asset)
                ->lockForUpdate()
                ->firstOrFail();
            $balance->reserved_for_routing = FinancialDecimal::add((string) $balance->reserved_for_routing, $amount);
            $balance->save();
            return;
        }

        $bucket = TreasuryLiquidityBucket::query()->where('asset', $asset)->where('bucket', $sourceCode)->lockForUpdate()->firstOrFail();
        $bucket->reserved_amount = FinancialDecimal::add((string) $bucket->reserved_amount, $amount);
        $bucket->save();
    }

    private function applyRelease(string $scope, string $sourceCode, string $asset, string $amount): void
    {
        if (strtoupper($scope) === 'EXTERNAL_VENUE') {
            $balance = ExternalVenueBalance::query()
                ->whereHas('account', fn ($query) => $query->where('venue', $sourceCode))
                ->where('asset', $asset)
                ->lockForUpdate()
                ->firstOrFail();
            $balance->reserved_for_routing = FinancialDecimal::max('0', FinancialDecimal::sub((string) $balance->reserved_for_routing, $amount));
            $balance->save();
            return;
        }

        $bucket = TreasuryLiquidityBucket::query()->where('asset', $asset)->where('bucket', $sourceCode)->lockForUpdate()->firstOrFail();
        $bucket->reserved_amount = FinancialDecimal::max('0', FinancialDecimal::sub((string) $bucket->reserved_amount, $amount));
        $bucket->save();
    }
}
