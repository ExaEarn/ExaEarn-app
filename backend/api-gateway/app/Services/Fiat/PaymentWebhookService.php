<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentWebhookService
{
    public function __construct(private readonly PaymentProviderRouter $router)
    {
    }

    public function accept(string $provider, array $payload, string $rawBody, array $headers): array
    {
        $paymentProvider = $this->router->provider($provider);
        $signatureOk = $paymentProvider->verifyWebhook($payload, $rawBody, $headers);
        if (!$signatureOk) {
            throw new RuntimeException('Invalid provider webhook signature.');
        }

        $normalized = $paymentProvider->normalizeWebhook($payload);
        if (($normalized['event_id'] ?? '') === '' || ($normalized['provider_transaction_id'] ?? '') === '') {
            throw new RuntimeException('Malformed provider webhook.');
        }

        return DB::transaction(function () use ($normalized, $payload, $paymentProvider, $rawBody): array {
            $existing = DB::table('provider_webhook_events')
                ->where('provider', $paymentProvider->key())
                ->where('event_id', (string) $normalized['event_id'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return (array) $existing;
            }

            $pk = DB::table('provider_webhook_events')->insertGetId([
                'event_uuid' => (string) Str::uuid(),
                'provider' => $paymentProvider->key(),
                'event_id' => (string) $normalized['event_id'],
                'event_type' => (string) $normalized['event_type'],
                'status' => 'ACCEPTED',
                'payload_hash' => hash('sha256', $rawBody),
                'signature_status' => 'VERIFIED',
                'normalized_payload' => json_encode($normalized, JSON_THROW_ON_ERROR),
                'metadata' => json_encode(['raw_payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('provider_webhook_events')->where('id', $pk)->first();
        });
    }
}
