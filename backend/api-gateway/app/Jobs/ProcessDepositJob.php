<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DepositService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDepositJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $currency,
        public readonly string $amount,
        public readonly ?string $reference,
        public readonly ?string $txHash,
        public readonly array $metadata = []
    ) {
    }

    public function handle(DepositService $depositService): void
    {
        $depositService->processDeposit(
            $this->userId,
            $this->currency,
            $this->amount,
            $this->reference,
            $this->txHash,
            $this->metadata
        );
    }
}
