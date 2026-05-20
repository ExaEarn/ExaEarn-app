<?php

namespace App\Services;

class WalletService
{
    /**
     * Get unified portfolio view.
     * 
     * @param int $userId
     * @return array
     */
    public function getUnifiedBalance(int $userId): array
    {
        // Aggregate balances across chains
        return [
            'BTC' => 0.5,
            'USDT' => 1000.0
        ];
    }
}
