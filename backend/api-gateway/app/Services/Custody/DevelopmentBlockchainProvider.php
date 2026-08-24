<?php

declare(strict_types=1);

namespace App\Services\Custody;

use RuntimeException;

class DevelopmentBlockchainProvider implements BlockchainProviderInterface
{
    public function providerId(): string
    {
        return 'development_provider';
    }

    public function providerState(string $network): string
    {
        return app()->environment(['local', 'testing']) ? 'HEALTHY' : 'OFFLINE';
    }

    public function healthCheck(): array
    {
        return ['state' => app()->environment(['local', 'testing']) ? 'HEALTHY' : 'OFFLINE'];
    }

    public function getLatestBlock(string $network): array
    {
        return ['network' => $network, 'height' => 1_000_000, 'hash' => hash('sha256', $network.'-block')];
    }

    public function getFinalizedHeight(string $network): int
    {
        return 999_900;
    }

    public function validateAddress(string $network, string $address, ?string $memoTag = null): bool
    {
        $network = strtolower($network);
        if ($network === 'xrpl' && ($memoTag === null || $memoTag === '')) {
            return false;
        }

        return match (config("custody.networks.{$network}.family")) {
            'evm' => (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $address),
            'utxo' => strlen($address) >= 26 && strlen($address) <= 90,
            'solana' => strlen($address) >= 32 && strlen($address) <= 44,
            'xrpl' => str_starts_with($address, 'r') && strlen($address) >= 25,
            'tron' => str_starts_with($address, 'T') && strlen($address) >= 30,
            default => false,
        };
    }

    public function getTransaction(string $network, string $txHash): array
    {
        if (!preg_match('/^[a-fA-F0-9]{32,128}$/', $txHash)) {
            throw new RuntimeException('Malformed transaction hash.');
        }

        return ['network' => $network, 'tx_hash' => $txHash, 'status' => 'CONFIRMED'];
    }

    public function getTransactionStatus(string $network, string $txHash): array
    {
        return ['network' => $network, 'tx_hash' => $txHash, 'status' => 'CONFIRMED', 'confirmations' => 100];
    }

    public function estimateNetworkFee(string $network, string $asset, string $amount): string
    {
        return (string) config('custody.fees.default_network_fee', '0');
    }

    public function broadcastTransaction(string $network, array $signedTransaction): array
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Development blockchain provider cannot broadcast outside local/testing.');
        }

        $payloadHash = hash('sha256', json_encode($signedTransaction, JSON_THROW_ON_ERROR));

        return ['tx_hash' => $payloadHash, 'status' => 'BROADCASTED', 'provider' => $this->providerId()];
    }

    public function getWalletBalance(string $network, string $asset, string $address): string
    {
        return '0';
    }

    public function getRequiredConfirmations(string $network, string $asset): int
    {
        return (int) config("custody.networks.{$network}.required_confirmations", 1);
    }
}
