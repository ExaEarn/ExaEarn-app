<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardInventory;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Gift Card Inventory Service
 *
 * Manages gift card inventory including checks, reservations, and fulfillment.
 */
class GiftCardInventoryService
{
    /**
     * Check available inventory for a brand and value.
     *
     * @param string $brand
     * @param float $cardValue
     * @param int $quantity
     * @return array{available: bool, count: int, required: int}
     */
    public function checkAvailability(string $brand, float $cardValue, int $quantity): array
    {
        $available = GiftCardInventory::query()
            ->where('brand', strtolower($brand))
            ->where('card_value', $cardValue)
            ->where('available', true)
            ->count();

        return [
            'available' => $available >= $quantity,
            'count' => $available,
            'required' => $quantity,
        ];
    }

    /**
     * Reserve gift cards for purchase (mark as temporarily reserved).
     *
     * @param string $brand
     * @param float $cardValue
     * @param int $quantity
     * @return Collection
     * @throws RuntimeException
     */
    public function reserveCards(string $brand, float $cardValue, int $quantity): Collection
    {
        $availability = $this->checkAvailability($brand, $cardValue, $quantity);
        if (!$availability['available']) {
            throw new RuntimeException(
                "Insufficient inventory. Required: {$quantity}, Available: {$availability['count']}"
            );
        }

        // Get cards to reserve (using database locking for consistency)
        $cardsToReserve = GiftCardInventory::query()
            ->where('brand', strtolower($brand))
            ->where('card_value', $cardValue)
            ->where('available', true)
            ->lockForUpdate()
            ->limit($quantity)
            ->get();

        if ($cardsToReserve->count() < $quantity) {
            throw new RuntimeException('Unable to reserve sufficient cards. Try again.');
        }

        // Mark cards as reserved by storing reservation metadata
        $cardsToReserve->each(function (GiftCardInventory $card) {
            $card->metadata = array_merge($card->metadata ?? [], [
                'reserved_at' => now()->toIso8601String(),
                'reservation_expires_at' => now()->addMinutes(15)->toIso8601String(),
            ]);
            $card->save();
        });

        return $cardsToReserve;
    }

    /**
     * Fulfill cards by marking them as sold.
     *
     * @param Collection $cards
     * @param int $userId
     * @param string $orderId
     * @return void
     */
    public function fulfillCards(Collection $cards, int $userId, string $orderId): void
    {
        $cards->each(function (GiftCardInventory $card) use ($userId, $orderId) {
            $card->update([
                'available' => false,
                'sold_at' => now(),
                'sold_to_user_id' => $userId,
                'metadata' => array_merge($card->metadata ?? [], [
                    'order_id' => $orderId,
                    'fulfilled_at' => now()->toIso8601String(),
                ]),
            ]);
        });
    }

    /**
     * Release reserved cards back to available inventory.
     *
     * @param Collection $cards
     * @return void
     */
    public function releaseReservation(Collection $cards): void
    {
        $cards->each(function (GiftCardInventory $card) {
            $metadata = $card->metadata ?? [];
            unset($metadata['reserved_at']);
            unset($metadata['reservation_expires_at']);

            $card->update([
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Get inventory summary by brand.
     *
     * @param string $brand
     * @return array
     */
    public function getInventorySummary(string $brand): array
    {
        $inventory = GiftCardInventory::query()
            ->where('brand', strtolower($brand))
            ->groupBy('card_value')
            ->selectRaw('card_value, COUNT(*) as total, SUM(CASE WHEN available = 1 THEN 1 ELSE 0 END) as available')
            ->get();

        $summary = [];
        foreach ($inventory as $item) {
            $summary[] = [
                'card_value' => (float) $item->card_value,
                'total_inventory' => (int) $item->total,
                'available' => (int) $item->available,
                'reserved' => (int) ($item->total - $item->available),
            ];
        }

        return $summary;
    }

    /**
     * Clean up expired reservations.
     *
     * @return int Number of reservations released
     */
    public function cleanupExpiredReservations(): int
    {
        $released = 0;
        $expiredCards = GiftCardInventory::query()
            ->whereJsonContains('metadata->reservation_expires_at', '<', now()->toIso8601String())
            ->where('available', false)
            ->get();

        foreach ($expiredCards as $card) {
            $this->releaseReservation(collect([$card]));
            $released++;
        }

        return $released;
    }

    /**
     * Add cards to inventory (from seller or vendor).
     *
     * @param string $brand
     * @param float $cardValue
     * @param string $encryptedCode
     * @param string|null $encryptedPin
     * @param int|null $submissionId
     * @param array $metadata
     * @return GiftCardInventory
     */
    public function addToInventory(
        string $brand,
        float $cardValue,
        string $encryptedCode,
        ?string $encryptedPin,
        ?int $submissionId,
        array $metadata = []
    ): GiftCardInventory {
        return GiftCardInventory::create([
            'brand' => strtolower($brand),
            'card_value' => $cardValue,
            'currency' => 'USD', // TODO: Support multi-currency
            'encrypted_card_code' => $encryptedCode,
            'encrypted_card_pin' => $encryptedPin,
            'submission_id' => $submissionId,
            'available' => true,
            'metadata' => $metadata,
        ]);
    }
}
