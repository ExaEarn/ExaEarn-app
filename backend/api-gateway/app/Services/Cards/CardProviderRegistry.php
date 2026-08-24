<?php

declare(strict_types=1);

namespace App\Services\Cards;

use RuntimeException;

class CardProviderRegistry
{
    public function provider(?string $name = null): CardProviderInterface
    {
        $provider = strtolower((string) ($name ?: config('exacard.provider_mode', 'sandbox')));

        if (in_array($provider, ['sandbox', 'fake'], true)) {
            return app(FakeCardProvider::class);
        }

        if (! (bool) config('exacard.production_issuance_enabled', false)) {
            throw new RuntimeException('Real ExaCard provider is not enabled. Configure a live provider and enable production issuance.');
        }

        throw new RuntimeException("No configured ExaCard provider adapter for {$provider}.");
    }

    public function capabilities(?string $provider = null): array
    {
        return $this->provider($provider)->capabilities();
    }
}
