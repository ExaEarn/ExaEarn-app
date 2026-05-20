<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TransactionType;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DistributeRewardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $currency,
        public readonly string $amount,
        public readonly TransactionType $type,
        public readonly ?string $reference = null,
        public readonly array $metadata = []
    ) {
    }

    public function handle(TransactionService $transactionService): void
    {
        $transactionService->recordReward(
            $this->userId,
            $this->type,
            $this->currency,
            $this->amount,
            $this->reference,
            $this->metadata
        );
    }
}
