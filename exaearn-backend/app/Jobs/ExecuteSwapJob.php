<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SwapEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteSwapJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $swapId)
    {
    }

    public function handle(SwapEngineService $swapEngineService): void
    {
        $swapEngineService->executeQueuedSwap($this->swapId);
    }
}
