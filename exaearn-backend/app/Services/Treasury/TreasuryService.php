<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\TreasuryTransaction;
use App\Models\TreasuryWallet;
use App\Models\WithdrawRequest;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TreasuryService
{
    public function __construct(
        private readonly WalletRepository $walletRepository,
        private readonly HotWalletService $hotWalletService,
        private readonly ColdWalletService $coldWalletService,
        private readonly WithdrawalSigner $withdrawalSigner,
    ) {
    }

    public function getHotBalance(string $chain, string $asset): string
    {
        $wallet = TreasuryWallet::where('type', 'hot')
            ->where('chain', $chain)
            ->where('status', 'active')
            ->first();

        if (!$wallet) {
            throw new RuntimeException("No active hot wallet found for chain {$chain}");
        }

        return $this->hotWalletService->getBalance($wallet, $asset);
    }

    public function getColdBalance(string $chain, string $asset): string
    {
        $wallet = TreasuryWallet::where('type', 'cold')
            ->where('chain', $chain)
            ->where('status', 'active')
            ->first();

        if (!$wallet) {
            throw new RuntimeException("No active cold wallet found for chain {$chain}");
        }

        return $this->coldWalletService->getBalance($wallet, $asset);
    }

    public function moveToCold(string $chain, string $asset, string $amount, int $adminId): TreasuryTransaction
    {
        return DB::transaction(function () use ($chain, $asset, $amount, $adminId): TreasuryTransaction {
            $hot = TreasuryWallet::where('type', 'hot')
                ->where('chain', $chain)
                ->where('status', 'active')
                ->first();

            $cold = TreasuryWallet::where('type', 'cold')
                ->where('chain', $chain)
                ->where('status', 'active')
                ->first();

            if (!$hot || !$cold) {
                throw new RuntimeException("Hot or cold wallet not configured for chain {$chain}");
            }

            $transaction = TreasuryTransaction::create([
                'type' => 'cold_move',
                'chain' => $chain,
                'amount' => $amount,
                'currency' => strtoupper($asset),
                'from_address' => $hot->address,
                'to_address' => $cold->address,
                'status' => 'pending',
            ]);

            $transaction->status = 'completed';
            $transaction->tx_hash = 'cold_move_' . $transaction->id;
            $transaction->save();

            $this->logTreasury('move_cold', [
                'admin_id' => $adminId,
                'transaction_id' => $transaction->id,
                'chain' => $chain,
                'asset' => $asset,
                'amount' => $amount,
                'from' => $hot->address,
                'to' => $cold->address,
            ]);

            return $transaction;
        });
    }

    public function moveToHot(string $chain, string $asset, string $amount, int $adminId): TreasuryTransaction
    {
        return DB::transaction(function () use ($chain, $asset, $amount, $adminId): TreasuryTransaction {
            $cold = TreasuryWallet::where('type', 'cold')
                ->where('chain', $chain)
                ->where('status', 'active')
                ->first();

            $hot = TreasuryWallet::where('type', 'hot')
                ->where('chain', $chain)
                ->where('status', 'active')
                ->first();

            if (!$cold || !$hot) {
                throw new RuntimeException("Cold or hot wallet not configured for chain {$chain}");
            }

            $transaction = TreasuryTransaction::create([
                'type' => 'hot_move',
                'chain' => $chain,
                'amount' => $amount,
                'currency' => strtoupper($asset),
                'from_address' => $cold->address,
                'to_address' => $hot->address,
                'status' => 'pending',
            ]);

            $transaction->status = 'completed';
            $transaction->tx_hash = 'hot_move_' . $transaction->id;
            $transaction->save();

            $this->logTreasury('move_hot', [
                'admin_id' => $adminId,
                'transaction_id' => $transaction->id,
                'chain' => $chain,
                'asset' => $asset,
                'amount' => $amount,
                'from' => $cold->address,
                'to' => $hot->address,
            ]);

            return $transaction;
        });
    }

    public function createWithdraw(int $userId, string $asset, string $amount, string $address): WithdrawRequest
    {
        $wallet = $this->walletRepository->lockWallet($userId, $asset);
        if (bccomp((string) $wallet->available_balance, $amount, 18) < 0) {
            throw new RuntimeException('Insufficient balance for withdrawal request.');
        }

        $rules = config('treasury.withdrawal_rules', []);
        $amountUSD = $this->convertToUsd($amount, $asset);
        $status = $this->determineWithdrawStatus($amountUSD, $rules);

        $request = WithdrawRequest::create([
            'user_id' => $userId,
            'asset' => strtoupper($asset),
            'amount' => $amount,
            'address' => $address,
            'status' => $status,
        ]);

        $this->logTreasury('withdraw_created', [
            'user_id' => $userId,
            'withdraw_id' => $request->id,
            'amount' => $amount,
            'asset' => $asset,
            'status' => $status,
        ]);

        return $request;
    }

    public function approveWithdraw(int $withdrawId, int $adminId): WithdrawRequest
    {
        $request = WithdrawRequest::findOrFail($withdrawId);
        $request->status = 'approved';
        $request->approved_by = $adminId;
        $request->save();

        $this->logTreasury('withdraw_approved', [
            'admin_id' => $adminId,
            'withdraw_id' => $withdrawId,
        ]);

        return $request;
    }

    public function signWithdraw(int $withdrawId, int $adminId): WithdrawRequest
    {
        $request = WithdrawRequest::findOrFail($withdrawId);

        if ($request->status !== 'approved') {
            throw new RuntimeException('Withdraw request must be approved before signing.');
        }

        $this->withdrawalSigner->dispatchSignJob($request, $adminId);

        $this->logTreasury('withdraw_sign_requested', [
            'admin_id' => $adminId,
            'withdraw_id' => $withdrawId,
        ]);

        return $request;
    }

    public function logTreasury(string $action, array $payload): void
    {
        Log::info("treasury.{$action}", array_merge($payload, [
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'timestamp' => now()->toISOString(),
        ]));
    }

    private function convertToUsd(string $amount, string $asset): float
    {
        $rates = config('treasury.asset_usd_rates', [
            'USDT' => 1.0,
            'USDC' => 1.0,
            'BTC' => 50000.0,
            'ETH' => 3000.0,
        ]);

        return (float) bcmul($amount, (string) ($rates[strtoupper($asset)] ?? 1.0), 2);
    }

    private function determineWithdrawStatus(float $amountUSD, array $rules): string
    {
        if ($amountUSD < ($rules['auto_threshold'] ?? 100)) {
            return 'approved';
        }

        return 'pending';
    }
}
