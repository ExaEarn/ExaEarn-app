<?php

declare(strict_types=1);

namespace App\Services\FiatPayout;

use App\Models\FiatWithdrawalIntent;
use App\Models\Withdrawal;
use App\Models\User;

class FiatPayoutProviderManager
{
    public function __construct(
        private readonly SandboxFiatPayoutProvider $sandboxProvider,
        private readonly LegacyFiatPayoutProvider $legacyProvider,
    ) {
    }

    public function provider(?string $key = null): FiatPayoutProviderInterface
    {
        $selected = strtolower((string) ($key ?: config('services.fiat_gateway.primary', 'sandbox')));

        return match ($selected) {
            'flutterwave', 'nomba' => $this->legacyProvider->forGateway($selected),
            default => $this->sandboxProvider,
        };
    }
}
