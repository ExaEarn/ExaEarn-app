<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Exception;
use RuntimeException;

class WalletService
{
    private const SCALE = 8;

    public function provisionWalletsForUser(\App\Models\User $user): void
    {
        Wallet::withoutEvents(function () use ($user): void {
            foreach ((array) config('wallet.assets', []) as $asset) {
                $code = strtoupper((string) ($asset['code'] ?? ''));

                if ($code === '') {
                    continue;
                }

                $this->getWallet((int) $user->id, $code);
            }
        });
    }

    public function getWallet(int $userId, string $currency): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId, 'currency' => strtoupper($currency)],
            ['available_balance' => '0', 'locked_balance' => '0']
        );
    }

    public function freezeFromTransaction(Transaction $transaction): Wallet
    {
        return DB::transaction(function () use ($transaction): Wallet {
            $wallet = Wallet::where('user_id', $transaction->user_id)
                ->where('currency', strtoupper((string) $transaction->currency))
                ->lockForUpdate()
                ->firstOr(fn () => $this->getWallet((int) $transaction->user_id, (string) $transaction->currency));

            if ($this->compare((string) $wallet->available_balance, (string) $transaction->amount) < 0) {
                throw new RuntimeException('Insufficient balance.');
            }

            $before = (string) $wallet->available_balance;
            $wallet->available_balance = $this->sub((string) $wallet->available_balance, (string) $transaction->amount);
            $wallet->locked_balance = $this->add((string) $wallet->locked_balance, (string) $transaction->amount);
            $wallet->save();

            $this->recordWalletTransaction($wallet, $transaction, $this->sub('0', (string) $transaction->amount), $before);

            return $wallet;
        });
    }

    public function unfreezeFromTransaction(Transaction $transaction): Wallet
    {
        return DB::transaction(function () use ($transaction): Wallet {
            $wallet = Wallet::where('user_id', $transaction->user_id)
                ->where('currency', strtoupper((string) $transaction->currency))
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->compare((string) $wallet->locked_balance, (string) $transaction->amount) < 0) {
                throw new RuntimeException('Insufficient locked balance.');
            }

            $before = (string) $wallet->available_balance;
            $wallet->locked_balance = $this->sub((string) $wallet->locked_balance, (string) $transaction->amount);
            $wallet->available_balance = $this->add((string) $wallet->available_balance, (string) $transaction->amount);
            $wallet->save();

            $this->recordWalletTransaction($wallet, $transaction, (string) $transaction->amount, $before);

            return $wallet;
        });
    }

    public function settleFrozenFromTransaction(Transaction $transaction): Wallet
    {
        return DB::transaction(function () use ($transaction): Wallet {
            $wallet = Wallet::where('user_id', $transaction->user_id)
                ->where('currency', strtoupper((string) $transaction->currency))
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->compare((string) $wallet->locked_balance, (string) $transaction->amount) < 0) {
                throw new RuntimeException('Insufficient locked balance.');
            }

            $before = (string) $wallet->locked_balance;
            $wallet->locked_balance = $this->sub((string) $wallet->locked_balance, (string) $transaction->amount);
            $wallet->save();

            $this->recordWalletTransaction($wallet, $transaction, $this->sub('0', (string) $transaction->amount), $before);

            return $wallet;
        });
    }

    public function creditFromTransaction(Transaction $transaction): Wallet
    {
        return DB::transaction(function () use ($transaction): Wallet {
            $wallet = Wallet::where('user_id', $transaction->user_id)
                ->where('currency', strtoupper((string) $transaction->currency))
                ->lockForUpdate()
                ->firstOr(fn () => $this->getWallet((int) $transaction->user_id, (string) $transaction->currency));

            $before = (string) $wallet->available_balance;
            $wallet->available_balance = $this->add((string) $wallet->available_balance, (string) $transaction->amount);
            $wallet->save();

            $this->recordWalletTransaction($wallet, $transaction, (string) $transaction->amount, $before);

            return $wallet;
        });
    }

    public function transfer(int $userId, string $asset, string $from, string $to, string $amount, string $referenceId)
    {
        return DB::transaction(function () use ($userId, $asset, $from, $to, $amount, $referenceId) {
            $balance = Balance::where('user_id', $userId)->where('asset', $asset)->lockForUpdate()->firstOrFail();
            LedgerTransaction::firstOrCreate(
                ['reference' => $referenceId],
                ['description' => 'Wallet internal transfer', 'status' => 'pending']
            );

            // Validate and Deduct
            $this->debit($balance, $from, $amount, $referenceId);
            
            // Credit
            $this->credit($balance, $to, $amount, $referenceId);
            
            $balance->save();
            LedgerTransaction::where('reference', $referenceId)->update(['status' => 'completed']);
            return $balance;
        });
    }

    private function debit(Balance $balance, string $walletType, string $amount, string $referenceId)
    {
        $field = "{$walletType}_available";
        if ($balance->$field < $amount) {
            throw new Exception("Insufficient funds");
        }

        $before = $balance->$field;
        $balance->$field -= $amount;
        $account = $this->account($balance, $walletType);
        $account->balance = (string) $balance->$field;
        $account->save();
        
        LedgerEntry::create([
            'account_id' => $account->id,
            'user_id' => $balance->user_id,
            'wallet_type' => $walletType,
            'asset' => $balance->asset,
            'amount' => (string) (-1 * (float) $amount),
            'type' => 'transfer',
            'transaction_type' => 'transfer',
            'reference' => $referenceId,
            'reference_id' => $referenceId,
            'balance_before' => $before,
            'balance_after' => $balance->$field,
        ]);
    }

    private function credit(Balance $balance, string $walletType, string $amount, string $referenceId)
    {
        $field = "{$walletType}_available";
        $before = $balance->$field;
        $balance->$field += $amount;
        $account = $this->account($balance, $walletType);
        $account->balance = (string) $balance->$field;
        $account->save();

        LedgerEntry::create([
            'account_id' => $account->id,
            'user_id' => $balance->user_id,
            'wallet_type' => $walletType,
            'asset' => $balance->asset,
            'amount' => $amount,
            'type' => 'transfer',
            'transaction_type' => 'transfer',
            'reference' => $referenceId,
            'reference_id' => $referenceId,
            'balance_before' => $before,
            'balance_after' => $balance->$field,
        ]);
    }

    private function account(Balance $balance, string $walletType): Account
    {
        return Account::firstOrCreate(
            [
                'user_id' => $balance->user_id,
                'account_type' => $walletType,
                'asset' => strtoupper($balance->asset),
            ],
            ['balance' => '0']
        );
    }

    private function recordWalletTransaction(Wallet $wallet, Transaction $transaction, string $amount, string $balanceBefore): void
    {
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => (string) $wallet->available_balance,
        ]);
    }

    private function add(string $left, string $right): string
    {
        return function_exists('bcadd') ? bcadd($left, $right, self::SCALE) : number_format((float) $left + (float) $right, self::SCALE, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        return function_exists('bcsub') ? bcsub($left, $right, self::SCALE) : number_format((float) $left - (float) $right, self::SCALE, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        return function_exists('bccomp') ? bccomp($left, $right, self::SCALE) : ((float) $left <=> (float) $right);
    }
}
