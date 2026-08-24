<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;

class PaymentProviderHealthService
{
    public function __construct(private readonly PaymentProviderRouter $router)
    {
    }

    public function refresh(?string $provider = null): array
    {
        $paymentProvider = $this->router->provider($provider);
        $health = $paymentProvider->healthCheck();
        DB::table('payment_provider_health')->updateOrInsert(
            ['provider' => $paymentProvider->key(), 'currency' => null, 'country' => null],
            [
                'state' => $health['state'] === 'HEALTHY' ? 'HEALTHY' : 'UNHEALTHY',
                'latency_ms' => 0,
                'success_rate' => '1',
                'checked_at' => now(),
                'metadata' => json_encode($health, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $health;
    }

    public function refreshAll(): array
    {
        app(PaymentProviderRouter::class)->syncConfiguredProviders();

        return DB::table('payment_provider_accounts')
            ->orderBy('provider')
            ->get()
            ->map(function (object $row): array {
                if (!in_array((string) $row->state, ['READY', 'LIVE', 'TESTING', 'SANDBOX'], true)) {
                    DB::table('payment_provider_health')->updateOrInsert(
                        ['provider' => (string) $row->provider, 'currency' => null, 'country' => null],
                        [
                            'state' => 'UNCONFIGURED',
                            'latency_ms' => 0,
                            'success_rate' => '0',
                            'checked_at' => now(),
                            'metadata' => json_encode(['state' => $row->state, 'reason' => 'provider_not_configured'], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );

                    return ['provider' => (string) $row->provider, 'state' => 'UNCONFIGURED'];
                }

                return $this->refresh((string) $row->provider);
            })
            ->all();
    }
}
