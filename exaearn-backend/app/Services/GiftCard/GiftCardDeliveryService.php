<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardInventory;
use App\Models\GiftcardOrder;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gift Card Delivery Service
 *
 * Handles secure delivery of gift cards to buyers.
 */
class GiftCardDeliveryService
{
    /**
     * Prepare cards for delivery.
     *
     * @param GiftcardOrder $order
     * @param array $cardIds
     * @return array
     */
    public function prepareDelivery(GiftcardOrder $order, array $cardIds): array
    {
        $cards = GiftCardInventory::query()
            ->whereIn('id', $cardIds)
            ->get();

        if ($cards->count() !== count($cardIds)) {
            throw new RuntimeException('One or more cards not found');
        }

        $deliverableCards = [];

        foreach ($cards as $card) {
            try {
                $decryptedCode = Crypt::decryptString($card->encrypted_card_code);
                $decryptedPin = $card->encrypted_card_pin
                    ? Crypt::decryptString($card->encrypted_card_pin)
                    : null;

                $deliverableCards[] = [
                    'id' => $card->id,
                    'brand' => $card->brand,
                    'card_value' => $card->card_value,
                    'currency' => $card->currency,
                    'card_code' => $this->maskCardCode($decryptedCode),
                    'card_code_full' => $decryptedCode,
                    'card_pin' => $decryptedPin ? $this->maskCardPin($decryptedPin) : null,
                    'card_pin_full' => $decryptedPin,
                    'delivered_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to decrypt gift card', [
                    'card_id' => $card->id,
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException("Failed to prepare card delivery for card ID {$card->id}");
            }
        }

        return $deliverableCards;
    }

    /**
     * Get in-app delivery response (masked for security).
     *
     * @param GiftcardOrder $order
     * @param array $deliverableCards
     * @return array
     */
    public function getInAppDelivery(GiftcardOrder $order, array $deliverableCards): array
    {
        return [
            'order_id' => $order->id,
            'status' => 'delivered',
            'delivered_at' => now()->toIso8601String(),
            'cards' => array_map(function ($card) {
                return [
                    'id' => $card['id'],
                    'brand' => $card['brand'],
                    'card_value' => $card['card_value'],
                    'card_code' => $card['card_code'],
                    'card_pin' => $card['card_pin'],
                ];
            }, $deliverableCards),
        ];
    }

    /**
     * Send email delivery (optional).
     *
     * @param User $user
     * @param GiftcardOrder $order
     * @param array $deliverableCards
     * @return bool
     */
    public function sendEmailDelivery(User $user, GiftcardOrder $order, array $deliverableCards): bool
    {
        try {
            // TODO: Implement email delivery using Laravel Mailable
            // This would send an email with the unmasked card details

            Log::info('Email delivery prepared', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'card_count' => count($deliverableCards),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email delivery', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark delivery as completed.
     *
     * @param GiftcardOrder $order
     * @param array $cardIds
     * @return void
     */
    public function completeDelivery(GiftcardOrder $order, array $cardIds): void
    {
        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'metadata' => array_merge($order->metadata ?? [], [
                'delivery_method' => 'in_app',
                'delivery_timestamp' => now()->toIso8601String(),
                'card_ids_delivered' => $cardIds,
            ]),
        ]);

        Log::info('Gift card order delivered', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'card_count' => count($cardIds),
        ]);
    }

    /**
     * Mask card code for display (show only last 4 digits).
     *
     * @param string $cardCode
     * @return string
     */
    private function maskCardCode(string $cardCode): string
    {
        $length = strlen($cardCode);
        if ($length <= 4) {
            return '****';
        }

        return str_repeat('*', $length - 4) . substr($cardCode, -4);
    }

    /**
     * Mask card PIN for display (show only last 2 digits).
     *
     * @param string $cardPin
     * @return string
     */
    private function maskCardPin(string $cardPin): string
    {
        $length = strlen($cardPin);
        if ($length <= 2) {
            return '**';
        }

        return str_repeat('*', $length - 2) . substr($cardPin, -2);
    }

    /**
     * Get full card details (unmasked) - restricted operation.
     *
     * @param GiftcardOrder $order
     * @param int $cardId
     * @return array
     */
    public function getFullCardDetails(GiftcardOrder $order, int $cardId): array
    {
        $card = GiftCardInventory::query()
            ->where('id', $cardId)
            ->where('sold_to_user_id', $order->user_id)
            ->firstOrFail();

        try {
            $decryptedCode = Crypt::decryptString($card->encrypted_card_code);
            $decryptedPin = $card->encrypted_card_pin
                ? Crypt::decryptString($card->encrypted_card_pin)
                : null;

            // Log access for security
            Log::info('Full card details accessed', [
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'card_id' => $cardId,
                'ip' => request()?->ip(),
            ]);

            return [
                'brand' => $card->brand,
                'card_value' => $card->card_value,
                'card_code' => $decryptedCode,
                'card_pin' => $decryptedPin,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve full card details', [
                'card_id' => $cardId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Unable to retrieve card details');
        }
    }
}
