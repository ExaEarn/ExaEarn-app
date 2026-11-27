<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

final class UnsupportedOfflineDeviceAttestationVerifier implements OfflineDeviceAttestationVerifier
{
    public function verify(string $deviceUuid, string $publicKeyFingerprint, string $challenge, array $attestation): array
    {
        // A fresh challenge alone is not hardware proof. Keep every platform
        // SOFTWARE_ONLY until chain, app identity, key binding and revocation roots
        // are verified by a configured platform adapter.
        return ['status' => 'UNSUPPORTED', 'trust_class' => 'SOFTWARE_ONLY', 'reason_code' => 'hardware_attestation_adapter_unavailable'];
    }
}
