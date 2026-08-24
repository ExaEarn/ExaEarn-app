<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use Illuminate\Support\Str;

class FakeGiftCardProvider implements GiftCardProviderInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function purchase(array $payload): array
    {
        $scenario = strtoupper((string) ($payload['scenario'] ?? config('giftcard.provider.fake_scenario', 'SUCCESS')));

        return match ($scenario) {
            'FAILED' => ['status' => 'FAILED', 'reason' => 'SANDBOX_PROVIDER_DECLINE'],
            'TIMEOUT', 'PROVIDER_UNKNOWN' => ['status' => 'PROVIDER_UNKNOWN', 'provider_reference' => 'fake_unknown_'.Str::uuid()],
            'OUT_OF_STOCK' => ['status' => 'OUT_OF_STOCK', 'reason' => 'SANDBOX_OUT_OF_STOCK'],
            default => [
                'status' => 'SUCCESS',
                'provider_reference' => 'fake_gc_'.Str::uuid(),
                'cards' => [[
                    'brand' => $payload['brand'] ?? 'sandbox',
                    'currency' => $payload['currency'] ?? 'USD',
                    'value' => $payload['card_value'] ?? '0',
                    'code' => 'SANDBOX-'.Str::upper(Str::random(18)),
                    'pin' => null,
                ]],
            ],
        };
    }

    public function checkOrder(string $providerReference): array
    {
        return ['status' => 'SUCCESS', 'provider_reference' => $providerReference];
    }

    public function refund(string $providerReference, array $payload = []): array
    {
        return ['status' => 'REFUNDED', 'provider_reference' => $providerReference, 'refund_reference' => 'fake_refund_'.Str::uuid()];
    }

    public function balance(string $currency): array
    {
        return ['provider' => $this->name(), 'currency' => strtoupper($currency), 'available' => '1000000.00000000', 'status' => 'sandbox'];
    }
}

