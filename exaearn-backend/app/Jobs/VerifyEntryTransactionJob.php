<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GameFiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyEntryTransactionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly int $recordId,
    ) {
        $this->onQueue('gamefi');
    }

    public function handle(GameFiService $gameFiService): void
    {
        $gameFiService->verifyEntryTransaction($this->type, $this->recordId);
    }
}
