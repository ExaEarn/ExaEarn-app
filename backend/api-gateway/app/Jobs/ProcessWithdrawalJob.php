<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\WithdrawalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWithdrawalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $transactionId,
        public readonly ?string $txHash = null,
        public readonly ?string $failureReason = null
    ) {
    }

    public function handle(WithdrawalService $withdrawalService): void
    {
        $transaction = Transaction::query()
            ->where('transaction_id', $this->transactionId)
            ->firstOrFail();

        if ($this->failureReason) {
            $withdrawalService->fail($transaction, $this->failureReason);
            return;
        }

        if ($this->txHash) {
            $withdrawalService->complete($transaction, $this->txHash);
            return;
        }

        $withdrawalService->broadcast($transaction);
    }
}
