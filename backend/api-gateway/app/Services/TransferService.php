<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InternalWalletTransaction;
use App\Models\Transaction;
use App\Models\LedgerTransaction;
use App\Models\WalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TransferService
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly SettlementService $settlement,
        private readonly BalanceProjectionService $projections,
    ) {
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
        $asset = strtoupper($asset);
        $amount = FinancialDecimal::normalize($amount, 8);

        DB::transaction(function () use ($amount, $asset, $fromWallet, $reference, $toWallet, $userId): void {
            $this->seedLedgerFromLegacyBalanceIfNeeded($userId, $fromWallet, $asset);

            try {
                $this->settlement->internalTransfer(
                    $userId,
                    $fromWallet,
                    $toWallet,
                    $asset,
                    $amount,
                    $reference,
                    ['source_service' => 'transfer_service', 'legacy_projection_sync' => true],
                );
            } catch (\RuntimeException $exception) {
                if ($exception->getMessage() === 'Insufficient available balance for reservation.') {
                    throw new \RuntimeException('Insufficient balance.', 0, $exception);
                }

                throw $exception;
            }

            $this->syncLegacyWalletBalance($userId, $fromWallet, $asset);
            $this->syncLegacyWalletBalance($userId, $toWallet, $asset);

            InternalWalletTransaction::query()->create([
                'user_id' => $userId,
                'type' => 'transfer_out',
                'wallet_type' => $fromWallet,
                'asset' => $asset,
                'amount' => $amount,
                'reference' => $reference,
                'description' => "Canonical ledger transfer to {$toWallet}",
            ]);

            InternalWalletTransaction::query()->create([
                'user_id' => $userId,
                'type' => 'transfer_in',
                'wallet_type' => $toWallet,
                'asset' => $asset,
                'amount' => $amount,
                'reference' => $reference,
                'description' => "Canonical ledger transfer from {$fromWallet}",
            ]);

            $this->publishWalletUpdate($userId, $fromWallet, $asset);
            $this->publishWalletUpdate($userId, $toWallet, $asset);
        });
    }

    private function seedLedgerFromLegacyBalanceIfNeeded(int $userId, string $walletType, string $asset): void
    {
        $legacy = WalletBalance::query()
            ->where('user_id', $userId)
            ->where('wallet_type', $walletType)
            ->where('asset', $asset)
            ->lockForUpdate()
            ->first();

        $legacyBalance = FinancialDecimal::normalize((string) ($legacy?->balance ?? '0'), 8);
        if (FinancialDecimal::compare($legacyBalance, '0', 8) <= 0) {
            return;
        }

        $ledger = app(LedgerService::class);
        $account = $ledger->getOrCreateAccount($userId, $walletType, $asset);
        if (FinancialDecimal::compare((string) $account->balance, $legacyBalance, 8) >= 0) {
            return;
        }

        $delta = FinancialDecimal::sub($legacyBalance, (string) $account->balance, 8);
        $reference = sprintf('LEGACY-WALLET-SEED-%d-%s-%s', $userId, strtoupper($walletType), strtoupper($asset));
        if (LedgerTransaction::query()->where('reference', $reference)->exists()) {
            return;
        }

        $migration = $ledger->getOrCreateAccount(null, 'legacy_wallet_migration', $asset);
        $ledger->postDoubleEntry($reference, 'Seed ledger from legacy wallet balance for internal transfer', [
            ['account_id' => $migration->id, 'amount' => FinancialDecimal::sub('0', $delta, 8), 'asset' => $asset, 'metadata' => ['legacy_wallet_type' => $walletType]],
            ['account_id' => $account->id, 'amount' => $delta, 'asset' => $asset, 'user_id' => $userId, 'metadata' => ['legacy_wallet_type' => $walletType]],
        ], 'migration', ['source_service' => 'transfer_service', 'legacy_wallet_type' => $walletType]);
    }
    private function syncLegacyWalletBalance(int $userId, string $walletType, string $asset): void
    {
        $projection = $this->projections->byUserAccountAndAsset($userId, $walletType, $asset);
        $wallet = WalletBalance::query()->firstOrCreate(
            ['user_id' => $userId, 'wallet_type' => $walletType, 'asset' => $asset],
            ['balance' => '0'],
        );
        $wallet = WalletBalance::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        $wallet->balance = FinancialDecimal::normalize((string) $projection['total'], 8);
        $wallet->save();
    }

    private function publishWalletUpdate(int $userId, string $walletType, string $asset): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            $projection = $this->projections->byUserAccountAndAsset($userId, $walletType, $asset);
            Redis::publish('wallet_updates', json_encode([
                'user_id' => $userId,
                'wallet_type' => $walletType,
                'asset' => $asset,
                'new_balance' => $projection['total'],
                'available' => $projection['available'],
                'reserved' => $projection['reserved'],
            ]));
        } catch (\Throwable $e) {
            \Log::warning('Failed to publish wallet update', ['error' => $e->getMessage()]);
        }
    }
}

