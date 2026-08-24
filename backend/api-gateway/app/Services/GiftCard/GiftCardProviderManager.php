<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use RuntimeException;

class GiftCardProviderManager
{
    public function provider(?string $name = null): GiftCardProviderInterface
    {
        $provider = strtolower((string) ($name ?: config('giftcard.provider.default', 'fake')));

        if ($provider === 'fake') {
            if (app()->environment(['production']) && (bool) config('giftcard.provider.fake_enabled', false) === false) {
                throw new RuntimeException('Real gift card provider is not configured. Automated fulfillment is disabled.');
            }

            return app(FakeGiftCardProvider::class);
        }

        throw new RuntimeException("Gift card provider [{$provider}] is not configured.");
    }

    public function productionReady(): bool
    {
        return app()->environment(['production'])
            ? strtolower((string) config('giftcard.provider.default', 'fake')) !== 'fake'
            : true;
    }
}

