<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentProviderRouter
{
    public function __construct(private readonly SandboxPaymentProvider $sandbox)
    {
    }

    public function provider(?string $requested = null, ?string $country = null, ?string $currency = null, ?string $capability = null): PaymentProviderInterface
    {
        $this->syncConfiguredProviders();
        if ($requested !== null && strtolower($requested) !== 'sandbox') {
            $row = DB::table('payment_provider_accounts')->where('provider', strtolower($requested))->first();
            if (!$row || !in_array($row->state, ['READY', 'LIVE', 'TESTING', 'SANDBOX'], true)) {
                throw new RuntimeException('Requested payment provider is not configured.');
            }
        }

        return $this->sandbox;
    }

    public function syncConfiguredProviders(): void
    {
        foreach ((array) config('fiat.providers', []) as $provider => $settings) {
            DB::table('payment_provider_accounts')->updateOrInsert(
                ['account_reference' => $provider.'-primary'],
                [
                    'provider' => $provider,
                    'environment' => $provider === 'sandbox' ? 'sandbox' : 'production',
                    'state' => (string) ($settings['state'] ?? 'CREDENTIALS_REQUIRED'),
                    'capabilities' => json_encode($settings['capabilities'] ?? [], JSON_THROW_ON_ERROR),
                    'secret_references' => json_encode(['configured_by_env' => $provider !== 'sandbox'], JSON_THROW_ON_ERROR),
                    'metadata' => json_encode($settings, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
