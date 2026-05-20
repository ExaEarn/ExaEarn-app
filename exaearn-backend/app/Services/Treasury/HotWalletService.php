<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\TreasuryWallet;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class HotWalletService
{
    public function getBalance(TreasuryWallet $wallet, string $asset): string
    {
        return (string) $wallet->metadata['balances'][strtoupper($asset)] ?? '0';
    }

    public function sendTransaction(TreasuryWallet $wallet, string $toAddress, string $amount, string $asset): string
    {
        $privateKey = $this->decryptPrivateKey($wallet);

        if ($privateKey === '') {
            throw new RuntimeException('Hot wallet private key is unavailable.');
        }

        return '0x' . hash('sha256', $wallet->address . $toAddress . $amount . $asset . time());
    }

    public function decryptPrivateKey(TreasuryWallet $wallet): string
    {
        $encryptedKey = $wallet->metadata['encrypted_private_key'] ?? '';
        if ($encryptedKey === '') {
            throw new RuntimeException('Encrypted private key is missing.');
        }

        $secret = config('treasury.security.key_secret');
        if ($secret === null || $secret === '') {
            throw new RuntimeException('Treasury key secret is not configured.');
        }

        return Crypt::decryptString($encryptedKey);
    }
}
