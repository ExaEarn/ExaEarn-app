<?php

declare(strict_types=1);

namespace App\Services\Custody;

interface BlockchainNetworkInterface
{
    public function healthCheck(): array;

    public function getLatestBlock(string $network): array;

    public function getFinalizedHeight(string $network): int;

    public function validateAddress(string $network, string $address, ?string $memoTag = null): bool;

    public function getTransaction(string $network, string $txHash): array;

    public function getTransactionStatus(string $network, string $txHash): array;

    public function estimateNetworkFee(string $network, string $asset, string $amount): string;

    public function broadcastTransaction(string $network, array $signedTransaction): array;

    public function getWalletBalance(string $network, string $asset, string $address): string;

    public function getRequiredConfirmations(string $network, string $asset): int;
}
