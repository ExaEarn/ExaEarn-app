<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Treasury\SweepService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SweepFundsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $fromAddress,
        public string $amount,
        public string $asset,
        public string $chain,
    ) {
    }

    public function handle(SweepService $sweepService): void
    {
        try {
            Log::info('SweepFundsJob started', [
                'from_address' => $this->fromAddress,
                'amount' => $this->amount,
                'asset' => $this->asset,
                'chain' => $this->chain,
            ]);

            $transaction = $sweepService->sweepToHot(
                $this->fromAddress,
                $this->amount,
                $this->asset,
                $this->chain
            );

            Log::info('SweepFundsJob completed', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $transaction->tx_hash,
            ]);
        } catch (\Throwable $exception) {
            Log::error('SweepFundsJob failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
