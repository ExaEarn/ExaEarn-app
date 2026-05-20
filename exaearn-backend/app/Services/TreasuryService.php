<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FiatTreasuryTransaction;
use App\Models\LiquidityLog;
use App\Models\TreasuryAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TreasuryService
{
    private const SCALE = 18;

    public function __construct()
    {
    }

    /**
     * Credit treasury account - NEVER allow negative balances
     */
    public function credit(string $provider, string $amount, string $currency, string $reference): FiatTreasuryTransaction
    {
        return DB::transaction(function () use ($provider, $amount, $currency, $reference) {
            $account = $this->getOrCreateAccount($provider, $currency);

            if ($account->status !== 'active') {
                throw new RuntimeException("Treasury account {$provider}:{$currency} is not active");
            }

            $account->available_balance = bcadd((string) $account->available_balance, $amount, self::SCALE);
            $account->save();

            $transaction = FiatTreasuryTransaction::create([
                'provider' => $provider,
                'type' => 'deposit',
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'status' => 'success',
                'meta_data' => [
                    'balance_before' => bcsub((string) $account->available_balance, $amount, self::SCALE),
                    'balance_after' => $account->available_balance,
                ],
            ]);

            Log::info("Treasury credit: {$provider}:{$currency} +{$amount}", [
                'reference' => $reference,
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }

    /**
     * Debit treasury account - NEVER allow negative balances
     */
    public function debit(string $provider, string $amount, string $currency, string $reference): FiatTreasuryTransaction
    {
        return DB::transaction(function () use ($provider, $amount, $currency, $reference) {
            $account = $this->getAccount($provider, $currency);

            if (!$account || $account->status !== 'active') {
                throw new RuntimeException("Treasury account {$provider}:{$currency} not found or not active");
            }

            if (bccomp((string) $account->available_balance, $amount, self::SCALE) < 0) {
                throw new RuntimeException("Insufficient treasury balance for {$provider}:{$currency}");
            }

            $account->available_balance = bcsub((string) $account->available_balance, $amount, self::SCALE);
            $account->save();

            $transaction = FiatTreasuryTransaction::create([
                'provider' => $provider,
                'type' => 'withdrawal',
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'status' => 'success',
                'meta_data' => [
                    'balance_before' => bcadd((string) $account->available_balance, $amount, self::SCALE),
                    'balance_after' => $account->available_balance,
                ],
            ]);

            Log::info("Treasury debit: {$provider}:{$currency} -{$amount}", [
                'reference' => $reference,
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }

    /**
     * Get balance for specific provider and currency
     */
    public function getBalance(string $provider, string $currency): string
    {
        $account = $this->getAccount($provider, $currency);
        return $account ? (string) $account->available_balance : '0';
    }

    /**
     * Get total balance across all providers for a currency
     */
    public function getTotalBalance(string $currency): string
    {
        return (string) TreasuryAccount::byCurrency($currency)
            ->active()
            ->sum('available_balance');
    }

    /**
     * Lock funds for pending operations
     */
    public function lockFunds(string $provider, string $amount, string $currency, string $reference): void
    {
        DB::transaction(function () use ($provider, $amount, $currency, $reference) {
            $account = $this->getAccount($provider, $currency);

            if (!$account || $account->status !== 'active') {
                throw new RuntimeException("Treasury account {$provider}:{$currency} not found or not active");
            }

            if (bccomp((string) $account->available_balance, $amount, self::SCALE) < 0) {
                throw new RuntimeException("Insufficient treasury balance for {$provider}:{$currency}");
            }

            $account->available_balance = bcsub((string) $account->available_balance, $amount, self::SCALE);
            $account->locked_balance = bcadd((string) $account->locked_balance, $amount, self::SCALE);
            $account->save();

            FiatTreasuryTransaction::create([
                'provider' => $provider,
                'type' => 'transfer',
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'status' => 'success',
                'meta_data' => [
                    'action' => 'lock_funds',
                    'available_before' => bcadd((string) $account->available_balance, $amount, self::SCALE),
                    'locked_before' => bcsub((string) $account->locked_balance, $amount, self::SCALE),
                ],
            ]);
        });
    }

    /**
     * Unlock funds after operation completion/failure
     */
    public function unlockFunds(string $provider, string $amount, string $currency, string $reference): void
    {
        DB::transaction(function () use ($provider, $amount, $currency, $reference) {
            $account = $this->getAccount($provider, $currency);

            if (!$account) {
                throw new RuntimeException("Treasury account {$provider}:{$currency} not found");
            }

            if (bccomp((string) $account->locked_balance, $amount, self::SCALE) < 0) {
                throw new RuntimeException("Insufficient locked balance for {$provider}:{$currency}");
            }

            $account->locked_balance = bcsub((string) $account->locked_balance, $amount, self::SCALE);
            $account->available_balance = bcadd((string) $account->available_balance, $amount, self::SCALE);
            $account->save();

            FiatTreasuryTransaction::create([
                'provider' => $provider,
                'type' => 'transfer',
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'status' => 'success',
                'meta_data' => [
                    'action' => 'unlock_funds',
                    'available_before' => bcsub((string) $account->available_balance, $amount, self::SCALE),
                    'locked_before' => bcadd((string) $account->locked_balance, $amount, self::SCALE),
                ],
            ]);
        });
    }

    /**
     * Get or create treasury account
     */
    private function getOrCreateAccount(string $provider, string $currency): TreasuryAccount
    {
        return TreasuryAccount::firstOrCreate(
            ['provider' => $provider, 'currency' => $currency],
            ['available_balance' => '0', 'locked_balance' => '0', 'status' => 'active']
        );
    }

    /**
     * Get treasury account
     */
    private function getAccount(string $provider, string $currency): ?TreasuryAccount
    {
        return TreasuryAccount::where('provider', $provider)
            ->where('currency', $currency)
            ->first();
    }

    /**
     * Get all active accounts for a currency
     */
    public function getActiveAccounts(string $currency): array
    {
        return TreasuryAccount::byCurrency($currency)
            ->active()
            ->get()
            ->toArray();
    }

    /**
     * Update account sync timestamp
     */
    public function updateSyncTimestamp(string $provider, string $currency): void
    {
        TreasuryAccount::where('provider', $provider)
            ->where('currency', $currency)
            ->update(['last_synced_at' => now()]);
    }
}