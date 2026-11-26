<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

final class UnsupportedOfflineDeviceAttestationVerifier implements OfflineDeviceAttestationVerifier
{
    public function verify(string $deviceUuid, string $publicKeyFingerprint, array $attestation): array
    {
        return ['status' => $attestation === [] ? 'UNSUPPORTED' : 'NOT_VERIFIED', 'trust_class' => 'SOFTWARE_ONLY', 'reason_code' => 'hardware_attestation_adapter_unavailable'];
    }
}
