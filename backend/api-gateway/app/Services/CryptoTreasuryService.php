<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TreasuryBalance;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CryptoTreasuryService
{
    private array $supportedChains;

    public function __construct()
    {
        $this->supportedChains = config('crypto.chains', []);
    }

    public function creditCrypto(string $asset, string $amount): void
    {
        $this->validateAsset($asset);

        $balance = $this->getOrCreateBalance($asset);
        $balance->increment('balance', $amount);
        $balance->increment('hot_wallet_balance', $amount); // Assume credits go to hot wallet

        $this->logTransaction('credit', $asset, $amount, ['source' => 'external']);
    }

    public function debitCrypto(string $asset, string $amount): void
    {
        $this->validateAsset($asset);

        $balance = $this->getBalanceModel($asset);
        if (!$balance || $balance->balance < $amount) {
            throw new RuntimeException("Insufficient balance for asset {$asset}");
        }

        // Check for large withdrawal
        if ($this->isLargeWithdrawal($asset, $amount)) {
            $this->requireMultiSignature($asset, $amount);
        }

        $balance->decrement('balance', $amount);
        $balance->decrement('hot_wallet_balance', $amount); // Assume debits from hot wallet

        $this->logTransaction('debit', $asset, $amount, ['destination' => 'external']);
    }

    public function getBalance(string $asset): string
    {
        $this->validateAsset($asset);

        $balance = $this->getBalanceModel($asset);
        return $balance ? $balance->balance : '0';
    }

    public function getHotWalletBalance(string $asset): string
    {
        $this->validateAsset($asset);

        $balance = $this->getBalanceModel($asset);
        return $balance ? $balance->hot_wallet_balance : '0';
    }

    public function getColdWalletBalance(string $asset): string
    {
        $this->validateAsset($asset);

        $balance = $this->getBalanceModel($asset);
        return $balance ? $balance->cold_wallet_balance : '0';
    }

    public function transferToColdWallet(string $asset, string $amount): void
    {
        $this->validateAsset($asset);

        $balance = $this->getBalanceModel($asset);
        if (!$balance || $balance->hot_wallet_balance < $amount) {
            throw new RuntimeException("Insufficient hot wallet balance for {$asset}");
        }

        $balance->decrement('hot_wallet_balance', $amount);
        $balance->increment('cold_wallet_balance', $amount);

        $this->logTransaction('transfer_to_cold', $asset, $amount);
    }

    public function transferFromColdWallet(string $asset, string $amount): void
    {
        $this->validateAsset($asset);

        $balance = $this->getBalanceModel($asset);
        if (!$balance || $balance->cold_wallet_balance < $amount) {
            throw new RuntimeException("Insufficient cold wallet balance for {$asset}");
        }

        $balance->decrement('cold_wallet_balance', $amount);
        $balance->increment('hot_wallet_balance', $amount);

        $this->logTransaction('transfer_from_cold', $asset, $amount);
    }

    private function validateAsset(string $asset): void
    {
        foreach ($this->supportedChains as $chain => $config) {
            if (in_array($asset, $config['assets'])) {
                return;
            }
        }
        throw new RuntimeException("Unsupported asset: {$asset}");
    }

    private function getOrCreateBalance(string $asset): TreasuryBalance
    {
        return TreasuryBalance::firstOrCreate(
            ['asset' => $asset],
            ['balance' => '0', 'hot_wallet_balance' => '0', 'cold_wallet_balance' => '0']
        );
    }

    private function getBalanceModel(string $asset): ?TreasuryBalance
    {
        return TreasuryBalance::where('asset', $asset)->first();
    }

    private function isLargeWithdrawal(string $asset, string $amount): bool
    {
        // In a real implementation, convert amount to USD using current prices
        // For now, use a simple threshold
        $threshold = config('crypto.large_withdrawal_threshold_usd', 10000);
        return (float) $amount > $threshold;
    }

    private function requireMultiSignature(string $asset, string $amount): void
    {
        // Placeholder for multi-signature logic
        // In real implementation, this would create a pending transaction
        // requiring multiple approvals
        Log::warning("Multi-signature required for large withdrawal: {$asset} {$amount}");

        // For now, throw exception. In production, this would queue for approval
        throw new RuntimeException("Multi-signature approval required for large withdrawal");
    }

    private function logTransaction(string $type, string $asset, string $amount, array $details = []): void
    {
        TreasuryTransaction::create([
            'type' => $type,
            'asset' => $asset,
            'amount' => $amount,
            'timestamp' => now(),
            'details' => json_encode($details),
        ]);

        Log::info("Treasury transaction: {$type} {$amount} {$asset}", $details);
    }

    // Methods for interacting with specific chains (placeholders)
    public function getChainConfig(string $chain): array
    {
        return $this->supportedChains[$chain] ?? [];
    }

    public function getEncryptedPrivateKey(string $chain): string
    {
        $config = $this->getChainConfig($chain);
        return $config['encrypted_private_key'] ?? '';
    }

    public function decryptPrivateKey(string $encryptedKey): string
    {
        return Crypt::decryptString($encryptedKey);
    }

    // Placeholder for blockchain interactions
    public function sendTransaction(string $chain, string $asset, string $to, string $amount): string
    {
        // In real implementation, this would interact with the blockchain
        // using web3 libraries or RPC calls
        Log::info("Sending transaction on {$chain}: {$amount} {$asset} to {$to}");

        // Return a mock transaction hash
        return '0x' . bin2hex(random_bytes(32));
    }
}