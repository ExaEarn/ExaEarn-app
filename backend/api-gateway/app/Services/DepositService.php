<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;

class DepositService
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly ReferralService $referrals,
    ) {
    }

    public function processDeposit(
        int $userId,
        string $currency,
        string $amount,
        ?string $reference,
        ?string $txHash,
        array $metadata = []
    ): Transaction {
        $transaction = $this->transactions->recordDeposit($userId, $currency, $amount, $reference, $txHash, $metadata);

        $this->referrals->queueQualifiedActivity($userId, 'first_deposit', [
            'event_key' => (string) ($txHash ?: $transaction->transaction_id),
            'transaction_id' => $transaction->transaction_id,
            'wallet_address' => $metadata['from_address'] ?? null,
            'ip_address' => request()?->ip(),
        ]);

        return $transaction;
    }
}
