<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

/** Future Android Keystore/StrongBox/Java Card adapter; software must not emulate these guarantees. */
interface OfflineSecureDeviceAdapter
{
    public function attestation(string $deviceUuid, string $nonce): array;
    public function monotonicCounter(string $deviceUuid): array;
    public function prepareAtomicValueOperation(string $deviceUuid, int $amountMinor, string $operationDigest): array;
    public function recoveryProof(string $deviceUuid, string $operationDigest): array;
}
