<?php

declare(strict_types=1);

namespace App\Domain\Staking\Contracts;

interface SecureSignerInterface
{
    public function requestSignature(string $asset, string $network, array $unsignedPayload, string $idempotencyKey): array;
}
