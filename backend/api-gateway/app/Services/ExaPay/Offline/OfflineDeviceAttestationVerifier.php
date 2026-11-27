<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

interface OfflineDeviceAttestationVerifier
{
    /** Platform adapters must return VERIFIED only after chain, nonce, app identity, key binding and revocation checks. */
    public function verify(string $deviceUuid, string $publicKeyFingerprint, string $challenge, array $attestation): array;
}
