<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

final class UnsupportedOfflineSecureDeviceAdapter implements OfflineSecureDeviceAdapter
{
    public function attestation(string $deviceUuid, string $nonce): array { return ['status' => 'UNSUPPORTED']; }
    public function monotonicCounter(string $deviceUuid): array { return ['status' => 'UNSUPPORTED']; }
    public function prepareAtomicValueOperation(string $deviceUuid, int $amountMinor, string $operationDigest): array { return ['status' => 'UNSUPPORTED']; }
    public function recoveryProof(string $deviceUuid, string $operationDigest): array { return ['status' => 'UNSUPPORTED']; }
}
