<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Repositories\TransactionRepository;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TransactionService
{
    private const SCALE = 8;
    private const EFFECT_CREDIT = 'credit';
    private const EFFECT_DEBIT = 'debit';
    private const EFFECT_LOCK = 'lock';
    private const EFFECT_RELEASE = 'release';
    private const EFFECT_SETTLE = 'settle';

    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly WalletRepository $wallets
    ) {
    }

    public function recordDeposit(
        int $userId,
        string $currency,
        string $amount,
        ?string $reference,
        ?string $txHash,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($userId, $currency, $amount, $reference, $txHash, $metadata) {
            if ($txHash && $this->transactions->findByTxHash($txHash)) {
                throw new RuntimeException('Duplicate transaction hash detected.');
            }

            $wallet = $this->wallets->lockWallet($userId, $currency);

            $before = $wallet->available_balance;
            $wallet->available_balance = $this->add((string) $wallet->available_balance, $amount);
            $wallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $userId,
                'type' => TransactionType::Deposit,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Completed,
                'reference' => $reference,
                'tx_hash' => $txHash,
                'metadata' => $metadata,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $wallet->available_balance,
            ]);

            $this->logAudit($userId, 'deposit_completed', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function createTransaction(
        int $userId,
        TransactionType $type,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        $transaction = $this->transactions->create([
            'transaction_id' => $this->generateTransactionId(),
            'user_id' => $userId,
            'type' => $type,
            'currency' => strtoupper($currency),
            'amount' => $amount,
            'fee' => '0',
            'status' => TransactionStatus::Pending,
            'reference' => $reference,
            'tx_hash' => null,
            'metadata' => $metadata,
        ]);

        $this->logAudit($userId, "{$type->value}_transaction_created", [
            'transaction_id' => $transaction->transaction_id,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
        ]);

        return $transaction;
    }

    public function confirmTransaction(Transaction $transaction, array $metadata = []): Transaction
    {
        $transaction->status = TransactionStatus::Completed;
        $transaction->metadata = array_merge($transaction->metadata ?? [], $metadata, [
            'confirmed_at' => now()->toISOString(),
        ]);
        $transaction->save();

        $this->logAudit($transaction->user_id, "{$transaction->type->value}_transaction_confirmed", [
            'transaction_id' => $transaction->transaction_id,
            'reference' => $transaction->reference,
        ]);

        return $transaction;
    }

    public function failTransaction(Transaction $transaction, string $reason, array $metadata = []): Transaction
    {
        $transaction->status = TransactionStatus::Failed;
        $transaction->metadata = array_merge($transaction->metadata ?? [], $metadata, [
            'failure_reason' => $reason,
            'failed_at' => now()->toISOString(),
        ]);
        $transaction->save();

        $this->logAudit($transaction->user_id, "{$transaction->type->value}_transaction_failed", [
            'transaction_id' => $transaction->transaction_id,
            'reason' => $reason,
        ]);

        return $transaction;
    }

    public function reverseTransaction(
        Transaction $transaction,
        TransactionType $reverseType,
        string $reason,
        array $metadata = []
    ): Transaction {
        $reversal = $this->recordReward(
            $transaction->user_id,
            $reverseType,
            $transaction->currency,
            (string) $transaction->amount,
            $transaction->reference,
            array_merge($metadata, [
                'reversed_transaction_id' => $transaction->transaction_id,
                'reversal_reason' => $reason,
            ])
        );

        $this->logAudit($transaction->user_id, "{$transaction->type->value}_transaction_reversed", [
            'transaction_id' => $transaction->transaction_id,
            'reversal_transaction_id' => $reversal->transaction_id,
            'reason' => $reason,
        ]);

        return $reversal;
    }

    public function applyPendingCredit(Transaction $transaction): Transaction
    {
        return $this->applyPendingWalletEffect($transaction, self::EFFECT_CREDIT);
    }

    public function applyPendingDebit(Transaction $transaction): Transaction
    {
        return $this->applyPendingWalletEffect($transaction, self::EFFECT_DEBIT);
    }

    public function freezePendingFunds(Transaction $transaction): Transaction
    {
        return $this->applyPendingWalletEffect($transaction, self::EFFECT_LOCK);
    }

    public function unfreezePendingFunds(Transaction $transaction): Transaction
    {
        return $this->applyPendingWalletEffect($transaction, self::EFFECT_RELEASE);
    }

    public function settlePendingLockedFunds(Transaction $transaction): Transaction
    {
        return $this->applyPendingWalletEffect($transaction, self::EFFECT_SETTLE);
    }

    public function recordReward(
        int $userId,
        TransactionType $type,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($userId, $type, $currency, $amount, $reference, $metadata) {
            $wallet = $this->wallets->lockWallet($userId, $currency);

            $before = $wallet->available_balance;
            $wallet->available_balance = $this->add((string) $wallet->available_balance, $amount);
            $wallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $userId,
                'type' => $type,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Completed,
                'reference' => $reference,
                'tx_hash' => null,
                'metadata' => $metadata,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $wallet->available_balance,
            ]);

            $this->logAudit($userId, 'reward_distributed', [
                'transaction_id' => $transaction->transaction_id,
                'type' => $type->value,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function recordDebit(
        int $userId,
        TransactionType $type,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($userId, $type, $currency, $amount, $reference, $metadata) {
            $wallet = $this->wallets->lockWallet($userId, $currency);

            if ($this->compare((string) $wallet->available_balance, $amount) < 0) {
                throw new RuntimeException('Insufficient balance.');
            }

            $before = $wallet->available_balance;
            $wallet->available_balance = $this->sub((string) $wallet->available_balance, $amount);
            $wallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $userId,
                'type' => $type,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Completed,
                'reference' => $reference,
                'tx_hash' => null,
                'metadata' => $metadata,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $this->sub('0', $amount),
                'balance_before' => $before,
                'balance_after' => $wallet->available_balance,
            ]);

            $this->logAudit($userId, "{$type->value}_debited", [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function recordLockedOperation(
        int $userId,
        TransactionType $type,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($userId, $type, $currency, $amount, $reference, $metadata) {
            $wallet = $this->wallets->lockWallet($userId, $currency);

            if ($this->compare((string) $wallet->available_balance, $amount) < 0) {
                throw new RuntimeException('Insufficient balance.');
            }

            $before = $wallet->available_balance;
            $wallet->available_balance = $this->sub((string) $wallet->available_balance, $amount);
            $wallet->locked_balance = $this->add((string) $wallet->locked_balance, $amount);
            $wallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $userId,
                'type' => $type,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Completed,
                'reference' => $reference,
                'tx_hash' => null,
                'metadata' => $metadata,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $this->sub('0', $amount),
                'balance_before' => $before,
                'balance_after' => $wallet->available_balance,
            ]);

            $this->logAudit($userId, "{$type->value}_locked", [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function releaseLockedFunds(
        int $userId,
        TransactionType $type,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($userId, $type, $currency, $amount, $reference, $metadata) {
            $wallet = $this->wallets->lockWallet($userId, $currency);

            if ($this->compare((string) $wallet->locked_balance, $amount) < 0) {
                throw new RuntimeException('Insufficient locked balance.');
            }

            $before = $wallet->available_balance;
            $wallet->locked_balance = $this->sub((string) $wallet->locked_balance, $amount);
            $wallet->available_balance = $this->add((string) $wallet->available_balance, $amount);
            $wallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $userId,
                'type' => $type,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Completed,
                'reference' => $reference,
                'tx_hash' => null,
                'metadata' => $metadata,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $wallet->available_balance,
            ]);

            $this->logAudit($userId, "{$type->value}_released", [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function settleLockedFunds(
        int $userId,
        TransactionType $type,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($userId, $type, $currency, $amount, $reference, $metadata) {
            $wallet = $this->wallets->lockWallet($userId, $currency);

            if ($this->compare((string) $wallet->locked_balance, $amount) < 0) {
                throw new RuntimeException('Insufficient locked balance.');
            }

            $before = $wallet->available_balance;
            $wallet->locked_balance = $this->sub((string) $wallet->locked_balance, $amount);
            $wallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $userId,
                'type' => $type,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Completed,
                'reference' => $reference,
                'tx_hash' => null,
                'metadata' => $metadata,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'amount' => '0',
                'balance_before' => $before,
                'balance_after' => $wallet->available_balance,
            ]);

            $this->logAudit($userId, "{$type->value}_settled", [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function recordInternalTransfer(
        int $fromUserId,
        int $toUserId,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return $this->recordPeerSettlement(
            $fromUserId,
            $toUserId,
            TransactionType::InternalTransfer,
            $currency,
            $amount,
            $reference,
            $metadata
        );
    }

    public function recordPeerSettlement(
        int $fromUserId,
        int $toUserId,
        TransactionType $type,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($fromUserId, $toUserId, $type, $currency, $amount, $reference, $metadata) {
            [$firstUser, $secondUser] = $fromUserId < $toUserId
                ? [$fromUserId, $toUserId]
                : [$toUserId, $fromUserId];

            $firstWallet = $this->wallets->lockWallet($firstUser, $currency);
            $secondWallet = $this->wallets->lockWallet($secondUser, $currency);

            $fromWallet = $fromUserId === $firstUser ? $firstWallet : $secondWallet;
            $toWallet = $toUserId === $firstUser ? $firstWallet : $secondWallet;

            if ($this->compare((string) $fromWallet->available_balance, $amount) < 0) {
                throw new RuntimeException('Insufficient balance.');
            }

            $fromBefore = $fromWallet->available_balance;
            $toBefore = $toWallet->available_balance;

            $fromWallet->available_balance = $this->sub((string) $fromWallet->available_balance, $amount);
            $toWallet->available_balance = $this->add((string) $toWallet->available_balance, $amount);
            $fromWallet->save();
            $toWallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $fromUserId,
                'type' => $type,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Completed,
                'reference' => $reference,
                'tx_hash' => null,
                'metadata' => array_merge($metadata, [
                    'to_user_id' => $toUserId,
                ]),
            ]);

            WalletTransaction::create([
                'wallet_id' => $fromWallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $this->sub('0', $amount),
                'balance_before' => $fromBefore,
                'balance_after' => $fromWallet->available_balance,
            ]);

            WalletTransaction::create([
                'wallet_id' => $toWallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'balance_before' => $toBefore,
                'balance_after' => $toWallet->available_balance,
            ]);

            $this->logAudit($fromUserId, "{$type->value}_sent", [
                'transaction_id' => $transaction->transaction_id,
                'to_user_id' => $toUserId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            $this->logAudit($toUserId, "{$type->value}_received", [
                'transaction_id' => $transaction->transaction_id,
                'from_user_id' => $fromUserId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function requestWithdrawal(
        int $userId,
        string $currency,
        string $amount,
        ?string $reference,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($userId, $currency, $amount, $reference, $metadata) {
            $wallet = $this->wallets->lockWallet($userId, $currency);

            if ($this->compare((string) $wallet->available_balance, $amount) < 0) {
                throw new RuntimeException('Insufficient balance.');
            }

            $before = $wallet->available_balance;
            $wallet->available_balance = $this->sub((string) $wallet->available_balance, $amount);
            $wallet->locked_balance = $this->add((string) $wallet->locked_balance, $amount);
            $wallet->save();

            $transaction = $this->transactions->create([
                'transaction_id' => $this->generateTransactionId(),
                'user_id' => $userId,
                'type' => TransactionType::Withdrawal,
                'currency' => strtoupper($currency),
                'amount' => $amount,
                'fee' => '0',
                'status' => TransactionStatus::Pending,
                'reference' => $reference,
                'tx_hash' => null,
                'metadata' => $metadata,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'amount' => $this->sub('0', $amount),
                'balance_before' => $before,
                'balance_after' => $wallet->available_balance,
            ]);

            $this->logAudit($userId, 'withdrawal_requested', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function completeWithdrawal(Transaction $transaction, ?string $txHash = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $txHash) {
            $wallet = $this->wallets->lockWallet($transaction->user_id, $transaction->currency);

            $wallet->locked_balance = $this->sub((string) $wallet->locked_balance, (string) $transaction->amount);
            $wallet->save();

            $transaction->status = TransactionStatus::Completed;
            $transaction->tx_hash = $txHash ?? $transaction->tx_hash;
            $transaction->save();

            $this->logAudit($transaction->user_id, 'withdrawal_completed', [
                'transaction_id' => $transaction->transaction_id,
                'tx_hash' => $transaction->tx_hash,
            ]);

            return $transaction;
        });
    }

    public function failWithdrawal(Transaction $transaction, string $reason): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $wallet = $this->wallets->lockWallet($transaction->user_id, $transaction->currency);

            $wallet->locked_balance = $this->sub((string) $wallet->locked_balance, (string) $transaction->amount);
            $wallet->available_balance = $this->add((string) $wallet->available_balance, (string) $transaction->amount);
            $wallet->save();

            $transaction->status = TransactionStatus::Failed;
            $transaction->metadata = array_merge($transaction->metadata ?? [], ['failure_reason' => $reason]);
            $transaction->save();

            $this->logAudit($transaction->user_id, 'withdrawal_failed', [
                'transaction_id' => $transaction->transaction_id,
                'reason' => $reason,
            ]);

            return $transaction;
        });
    }

    private function generateTransactionId(): string
    {
        return strtoupper(Str::uuid()->toString());
    }

    private function applyPendingWalletEffect(Transaction $transaction, string $effect): Transaction
    {
        return DB::transaction(function () use ($transaction, $effect): Transaction {
            $freshTransaction = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($freshTransaction->status !== TransactionStatus::Pending && $freshTransaction->status !== TransactionStatus::Processing) {
                throw new RuntimeException('Transaction is not pending wallet application.');
            }

            $effectAlreadyApplied = (bool) data_get($freshTransaction->metadata, 'wallet_effect_applied', false);
            $currentEffect = (string) data_get($freshTransaction->metadata, 'wallet_effect', '');

            if ($effectAlreadyApplied && $currentEffect === $effect) {
                return $freshTransaction;
            }

            $allowedFollowUp = $effectAlreadyApplied
                && $currentEffect === self::EFFECT_LOCK
                && in_array($effect, [self::EFFECT_RELEASE, self::EFFECT_SETTLE], true);

            if ($effectAlreadyApplied && !$allowedFollowUp) {
                throw new RuntimeException('Wallet effect already applied for this transaction.');
            }

            $wallet = $this->wallets->lockWallet($freshTransaction->user_id, $freshTransaction->currency);
            $beforeAvailable = (string) $wallet->available_balance;
            $beforeLocked = (string) $wallet->locked_balance;
            $amount = (string) $freshTransaction->amount;

            if ($effect === self::EFFECT_CREDIT) {
                $wallet->available_balance = $this->add($beforeAvailable, $amount);
            } elseif ($effect === self::EFFECT_DEBIT) {
                if ($this->compare($beforeAvailable, $amount) < 0) {
                    throw new RuntimeException('Insufficient balance.');
                }
                $wallet->available_balance = $this->sub($beforeAvailable, $amount);
            } elseif ($effect === self::EFFECT_LOCK) {
                if ($this->compare($beforeAvailable, $amount) < 0) {
                    throw new RuntimeException('Insufficient balance.');
                }
                $wallet->available_balance = $this->sub($beforeAvailable, $amount);
                $wallet->locked_balance = $this->add($beforeLocked, $amount);
            } elseif ($effect === self::EFFECT_RELEASE) {
                if ($this->compare($beforeLocked, $amount) < 0) {
                    throw new RuntimeException('Insufficient locked balance.');
                }
                $wallet->locked_balance = $this->sub($beforeLocked, $amount);
                $wallet->available_balance = $this->add($beforeAvailable, $amount);
            } elseif ($effect === self::EFFECT_SETTLE) {
                if ($this->compare($beforeLocked, $amount) < 0) {
                    throw new RuntimeException('Insufficient locked balance.');
                }
                $wallet->locked_balance = $this->sub($beforeLocked, $amount);
            } else {
                throw new RuntimeException('Unsupported wallet effect.');
            }

            $wallet->save();

            $walletAmount = match ($effect) {
                self::EFFECT_CREDIT, self::EFFECT_RELEASE => $amount,
                self::EFFECT_DEBIT, self::EFFECT_LOCK => $this->sub('0', $amount),
                self::EFFECT_SETTLE => '0',
                default => '0',
            };

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $freshTransaction->id,
                'amount' => $walletAmount,
                'balance_before' => $beforeAvailable,
                'balance_after' => $wallet->available_balance,
            ]);

            $freshTransaction->status = TransactionStatus::Processing;
            $freshTransaction->metadata = array_merge($freshTransaction->metadata ?? [], [
                'wallet_effect' => $effect,
                'wallet_effect_applied' => true,
                'wallet_effect_applied_at' => now()->toISOString(),
                'wallet_locked_balance_before' => $beforeLocked,
                'wallet_locked_balance_after' => (string) $wallet->locked_balance,
            ]);
            $freshTransaction->save();

            $this->logAudit($freshTransaction->user_id, "{$freshTransaction->type->value}_wallet_{$effect}", [
                'transaction_id' => $freshTransaction->transaction_id,
                'amount' => $amount,
                'currency' => $freshTransaction->currency,
            ]);

            return $freshTransaction;
        });
    }

    private function logAudit(int $userId, string $action, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => request()?->ip(),
            'device' => request()?->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, self::SCALE);
        }

        return number_format(((float) $left + (float) $right), self::SCALE, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        if (function_exists('bcsub')) {
            return bcsub($left, $right, self::SCALE);
        }

        return number_format(((float) $left - (float) $right), self::SCALE, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, self::SCALE);
        }

        $leftFloat = (float) $left;
        $rightFloat = (float) $right;

        return $leftFloat < $rightFloat ? -1 : ($leftFloat > $rightFloat ? 1 : 0);
    }
}
