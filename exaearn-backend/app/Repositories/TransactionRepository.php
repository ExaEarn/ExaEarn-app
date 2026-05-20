<?php

// declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionRepository
{
    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }

    public function findByTransactionId(string $transactionId): ?Transaction
    {
        return Transaction::query()->where('transaction_id', $transactionId)->first();
    }

    public function findByTxHash(string $txHash): ?Transaction
    {
        return Transaction::query()->where('tx_hash', $txHash)->first();
    }

    public function listByUser(int $userId): Builder
    {
        return Transaction::query()->where('user_id', $userId)->latest();
    }

    public function markStatus(Transaction $transaction, TransactionStatus $status): Transaction
    {
        $transaction->status = $status;
        $transaction->save();

        return $transaction;
    }
}