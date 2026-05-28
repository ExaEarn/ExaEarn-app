<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InternalWalletTransaction;
use App\Models\Transaction;
use App\Models\WalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TransferService
{
    public function __construct(private readonly TransactionService $transactions)
    {
    }

    public function transfer(
        int $fromUserId,
        int $toUserId,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return $this->transactions->recordInternalTransfer(
            $fromUserId,
            $toUserId,
            $currency,
            $amount,
            $reference,
            $metadata
        );
    }

    public function internalTransfer(
        int $userId,
        string $fromWallet,
        string $toWallet,
        string $asset,
        string $amount
    ): void {
        if ($fromWallet === $toWallet) {
            throw new \InvalidArgumentException('Cannot transfer to the same wallet type.');
        }

        $reference = Str::uuid()->toString();

        DB::transaction(function () use ($userId, $fromWallet, $toWallet, $asset, $amount, $reference) {
            // Lock and get source wallet
            $query = WalletBalance::where('user_id', $userId)
                ->where('wallet_type', $fromWallet)
                ->where('asset', $asset);
            if (!app()->environment('testing')) {
                $query->lockForUpdate();
            }
            $sourceWallet = $query->first();

            if (!$sourceWallet || bccomp((string)$sourceWallet->balance, $amount, 8) < 0) {
                throw new \RuntimeException('Insufficient balance.');
            }

            // Lock and get destination wallet, create if not exists
            $destWallet = WalletBalance::firstOrCreate(
                ['user_id' => $userId, 'wallet_type' => $toWallet, 'asset' => $asset],
                ['balance' => '0']
            );
            if (!app()->environment('testing')) {
                $destWallet = $destWallet->lockForUpdate()->find($destWallet->id);
            }

            // Deduct from source
            $sourceWallet->balance = bcsub((string)$sourceWallet->balance, $amount, 8);
            $sourceWallet->save();

            // Add to destination
            $destWallet->balance = bcadd((string)$destWallet->balance, $amount, 8);
            $destWallet->save();

            // Log transactions
            InternalWalletTransaction::create([
                'user_id' => $userId,
                'type' => 'transfer_out',
                'wallet_type' => $fromWallet,
                'asset' => $asset,
                'amount' => $amount,
                'reference' => $reference,
                'description' => "Transfer to {$toWallet}",
            ]);

            InternalWalletTransaction::create([
                'user_id' => $userId,
                'type' => 'transfer_in',
                'wallet_type' => $toWallet,
                'asset' => $asset,
                'amount' => $amount,
                'reference' => $reference,
                'description' => "Transfer from {$fromWallet}",
            ]);

            // Publish to Redis
            if (!app()->environment('testing')) {
                try {
                    Redis::publish('wallet_updates', json_encode([
                        'user_id' => $userId,
                        'wallet_type' => $fromWallet,
                        'asset' => $asset,
                        'new_balance' => $sourceWallet->balance,
                    ]));

                    Redis::publish('wallet_updates', json_encode([
                        'user_id' => $userId,
                        'wallet_type' => $toWallet,
                        'asset' => $asset,
                        'new_balance' => $destWallet->balance,
                    ]));
                } catch (\Exception $e) {
                    // Log but don't fail the transfer
                    \Log::warning('Failed to publish wallet update', ['error' => $e->getMessage()]);
                }
            }
        });
    }
}
