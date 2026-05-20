<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\TreasuryWallet;
use RuntimeException;

class ColdWalletService
{
    public function getBalance(TreasuryWallet $wallet, string $asset): string
    {
        return (string) $wallet->metadata['balances'][strtoupper($asset)] ?? '0';
    }

    public function getAddress(TreasuryWallet $wallet): string
    {
        return $wallet->address;
    }

    public function validateAddress(string $address, string $chain): bool
    {
        return strlen($address) >= 26 && strlen($address) <= 64;
    }
}
