<?php

declare(strict_types=1);

namespace App\Services;

class BlockchainApiService extends BlockchainService
{
    public function executeContract(string $method, array $params = [], string $contract = 'lottery', string $network = 'base', ?string $value = null): array
    {
        return $this->postNode('/contracts/execute', array_filter([
            'contract' => $contract,
            'method' => $method,
            'params' => $params,
            'network' => $network,
            'value' => $value,
        ], static fn ($item) => $item !== null));
    }

    public function callContract(string $method, array $params = [], string $contract = 'lottery', string $network = 'base'): array
    {
        return $this->postNode('/contracts/call', [
            'contract' => $contract,
            'method' => $method,
            'params' => $params,
            'network' => $network,
        ]);
    }

    public function getTransactionStatus(string $txHash, string $network = 'base'): array
    {
        return $this->getNode('/transactions/' . rawurlencode($txHash) . '/status?network=' . rawurlencode($network));
    }
}
