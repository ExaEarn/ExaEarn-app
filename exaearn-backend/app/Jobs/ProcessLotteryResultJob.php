<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GameFiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessLotteryResultJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $gameId)
    {
        $this->onQueue('gamefi');
    }

    public function handle(GameFiService $gameFiService): void
    {
        $gameFiService->syncLotteryResult($this->gameId);
    }
}
