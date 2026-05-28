<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\TreasuryTransaction;
use App\Models\TreasuryWallet;
use RuntimeException;

class SweepService
{
    public function sweepToHot(string $fromAddress, string $amount, string $asset, string $chain): TreasuryTransaction
    {
        $hotWallet = TreasuryWallet::where('type', 'hot')
            ->where('chain', $chain)
            ->where('status', 'active')
            ->first();

        if (!$hotWallet) {
            throw new RuntimeException("No hot wallet configured for chain {$chain}");
        }

        $transaction = TreasuryTransaction::create([
            'type' => 'sweep',
            'chain' => $chain,
            'amount' => $amount,
            'currency' => strtoupper($asset),
            'from_address' => $fromAddress,
            'to_address' => $hotWallet->address,
            'status' => 'completed',
            'tx_hash' => 'sweep_' . sha1($fromAddress . $hotWallet->address . $amount . time()),
        ]);

        return $transaction;
    }
}
