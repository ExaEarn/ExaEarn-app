<?php

declare(strict_types=1);

namespace App\Services\Custody;

interface SigningProviderInterface
{
    public function providerId(): string;

    public function healthCheck(): array;

    public function getPublicKey(string $network, string $walletReference): string;

    public function signTransaction(string $network, array $unsignedTransaction, array $policyContext = []): array;
}
