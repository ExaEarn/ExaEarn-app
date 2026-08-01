<?php

declare(strict_types=1);

namespace App\Domain\Staking\Services;

use App\Domain\Staking\Contracts\SecureSignerInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSecureSigner implements SecureSignerInterface
{
    public function requestSignature(string $asset, string $network, array $unsignedPayload, string $idempotencyKey): array
    {
        $url = rtrim((string) config('services.staking_secure_signer.url', ''), '/');
        $keyReference = (string) config('services.staking_secure_signer.key_reference', '');
        $secret = (string) config('services.staking_secure_signer.secret', '');

        if ($url === '' || $keyReference === '' || $secret === '') {
            throw new RuntimeException('Secure staking signer is not configured.');
        }

        $response = Http::timeout((int) config('services.staking_secure_signer.timeout_seconds', 15))
            ->acceptJson()
            ->withHeaders([
                'X-Signer-Secret' => $secret,
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->post($url.'/sign', [
                'asset' => strtoupper($asset),
                'network' => strtolower($network),
                'key_reference' => $keyReference,
                'unsigned_payload' => $unsignedPayload,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Secure staking signer request failed.');
        }

        $data = $response->json();
        if (! is_array($data) || ! is_string($data['signed_payload'] ?? null) || ! is_string($data['signing_reference'] ?? null)) {
            throw new RuntimeException('Secure staking signer returned an invalid response.');
        }

        return [
            'signed_payload' => $data['signed_payload'],
            'signing_reference' => $data['signing_reference'],
            'status' => (string) ($data['status'] ?? 'signed'),
            'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        ];
    }
}
