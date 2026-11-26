<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflinePaymentException;

/** Staging/test adapter. Replace by a KMS/HSM adapter before production use. */
final class ConfiguredOpenSslIssuerSigningProvider implements OfflineIssuerSigningProvider
{
    public function sign(array $payload): array
    {
        $privatePem = config('exapay_offline.issuer_private_key');
        if (!is_string($privatePem) || trim($privatePem) === '') {
            throw new OfflinePaymentException('issuer_unavailable', 'Offline instrument issuance is not configured.', 503);
        }
        $key = openssl_pkey_get_private(str_replace('\\n', "\n", $privatePem), (string) config('exapay_offline.issuer_private_key_passphrase', ''));
        if ($key === false) throw new OfflinePaymentException('issuer_unavailable', 'Offline instrument issuance is not configured.', 503);
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !$this->supportedKey($details)) throw new OfflinePaymentException('issuer_key_weak', 'The offline issuer signing key does not meet policy.', 503);
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!openssl_sign($canonical, $signature, $key, OPENSSL_ALGO_SHA256)) throw new OfflinePaymentException('issuer_signing_failed', 'The offline instrument could not be signed.', 503);
        return ['canonical_payload' => $canonical, 'signature' => base64_encode($signature), 'issuer_public_key' => (string) ($details['key'] ?? ''), 'signature_algorithm' => 'SHA256'];
    }

    public function publicKey(): ?string
    {
        $pem = config('exapay_offline.issuer_private_key');
        if (!is_string($pem) || trim($pem) === '') return null;
        $key = openssl_pkey_get_private(str_replace('\\n', "\n", $pem), (string) config('exapay_offline.issuer_private_key_passphrase', ''));
        if ($key === false) return null;
        $details = openssl_pkey_get_details($key);
        return is_array($details) && $this->supportedKey($details) ? (string) ($details['key'] ?? '') : null;
    }

    private function supportedKey(array $details): bool
    {
        return match ($details['type'] ?? null) {
            OPENSSL_KEYTYPE_RSA => (int) ($details['bits'] ?? 0) >= 2048,
            OPENSSL_KEYTYPE_EC => (int) ($details['bits'] ?? 0) >= 256,
            default => false,
        };
    }
}
