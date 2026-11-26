<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflinePaymentException;

final class OfflineInstrumentCrypto
{
    public function __construct(private readonly OfflineIssuerSigningProvider $issuerSigner)
    {
    }

    public function publicKeyDetails(string $pem): array
    {
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new OfflinePaymentException('invalid_device_key', 'The device public key is invalid.', 422);
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !$this->supportedKeyDetails($details)) {
            throw new OfflinePaymentException('unsupported_device_key', 'Use an RSA-2048+ or P-256+ device key.', 422);
        }

        return $details;
    }

    public function verifyDeviceProof(string $pem, string $message, string $signature): bool
    {
        $details = $this->publicKeyDetails($pem);
        $rawSignature = base64_decode($signature, true);
        if ($rawSignature === false || $rawSignature === '') {
            return false;
        }
        $key = openssl_pkey_get_public($pem);

        return $key !== false && openssl_verify($message, $rawSignature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public function signIssuerPayload(array $payload): array
    {
        return $this->issuerSigner->sign($payload);
    }

    public function verifyIssuerPayload(string $publicPem, string $canonicalPayload, string $signature): bool
    {
        $publicKey = openssl_pkey_get_public($publicPem);
        if ($publicKey === false) return false;
        $details = openssl_pkey_get_details($publicKey);
        $rawSignature = base64_decode($signature, true);
        if (!is_array($details) || !$this->supportedKeyDetails($details) || $rawSignature === false || $rawSignature === '') return false;

        return openssl_verify($canonicalPayload, $rawSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    public function issuerPublicKey(): ?string
    {
        return $this->issuerSigner->publicKey();
    }

    private function supportedKeyDetails(array $details): bool
    {
        return match ($details['type'] ?? null) {
            OPENSSL_KEYTYPE_RSA => (int) ($details['bits'] ?? 0) >= 2048,
            OPENSSL_KEYTYPE_EC => (int) ($details['bits'] ?? 0) >= 256,
            default => false,
        };
    }
}
