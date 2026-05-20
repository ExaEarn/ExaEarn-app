<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardSubmission;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GiftCardValidationService
{
    /**
     * Validate gift card via external API or manual verification.
     *
     * @param string $brand
     * @param string $cardCode
     * @param string|null $cardPin
     * @return array ['valid' => bool, 'balance' => decimal|null, 'message' => string]
     */
    public function validate(string $brand, string $cardCode, ?string $cardPin = null): array
    {
        // Check for duplicates
        if ($this->isDuplicate($brand, $cardCode)) {
            return [
                'valid' => false,
                'message' => 'This gift card has already been submitted.',
                'requires_manual_review' => false,
            ];
        }

        // Attempt external API validation if configured
        $result = $this->validateExternalAPI($brand, $cardCode, $cardPin);

        if ($result !== null) {
            if (!isset($result['requires_manual_review'])) {
                $result['requires_manual_review'] = false;
            }

            return $result;
        }

        // Fall back to manual verification required
        return [
            'valid' => false,
            'message' => 'External validation unavailable. Manual review required.',
            'requires_manual_review' => true,
        ];
    }

    /**
     * Check if card code already exists in system.
     *
     * @param string $cardCode
     * @return bool
     */
    private function isDuplicate(string $brand, string $cardCode): bool
    {
        $cardHash = $this->hashCardCode($brand, $cardCode);

        return GiftCardSubmission::where('card_hash', $cardHash)
            ->whereIn('status', ['pending', 'verifying', 'approved', 'paid_out'])
            ->exists();
    }

    /**
     * Generate a stable hash for card code and brand.
     *
     * @param string $brand
     * @param string $cardCode
     * @return string
     */
    public function hashCardCode(string $brand, string $cardCode): string
    {
        return hash('sha256', strtolower(trim($brand)) . '|' . trim($cardCode));
    }

    /**
     * Validate via external API.
     *
     * @param string $brand
     * @param string $cardCode
     * @param string|null $cardPin
     * @return array|null
     */
    private function validateExternalAPI(string $brand, string $cardCode, ?string $cardPin = null): ?array
    {
        $apiUrl = config('giftcard.validation_apis.' . strtolower($brand));

        if (!$apiUrl) {
            Log::warning('No validation API configured for brand', ['brand' => $brand]);
            return null;
        }

        try {
            $response = Http::timeout(10)->post($apiUrl, [
                'card_code' => $cardCode,
                'card_pin' => $cardPin,
                'api_key' => config('giftcard.api_key'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'valid' => $data['valid'] ?? false,
                    'balance' => $data['balance'] ?? null,
                    'message' => $data['message'] ?? 'Validation completed',
                    'verification_data' => $data,
                ];
            }

            Log::error('External validation failed', [
                'brand' => $brand,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('External validation error', [
                'brand' => $brand,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Encrypt card data for storage.
     *
     * @param string $cardCode
     * @param string|null $cardPin
     * @return array ['encrypted_code' => string, 'encrypted_pin' => string|null]
     */
    public function encryptCardData(string $cardCode, ?string $cardPin = null): array
    {
        return [
            'encrypted_code' => Crypt::encryptString($cardCode),
            'encrypted_pin' => $cardPin ? Crypt::encryptString($cardPin) : null,
        ];
    }

    /**
     * Decrypt card data.
     *
     * @param string $encryptedCode
     * @param string|null $encryptedPin
     * @return array ['card_code' => string, 'card_pin' => string|null]
     */
    public function decryptCardData(string $encryptedCode, ?string $encryptedPin = null): array
    {
        return [
            'card_code' => Crypt::decryptString($encryptedCode),
            'card_pin' => $encryptedPin ? Crypt::decryptString($encryptedPin) : null,
        ];
    }
}