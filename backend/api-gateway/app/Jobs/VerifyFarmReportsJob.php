<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AgriService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerifyFarmReportsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $trackingId)
    {
    }

    public function handle(AgriService $agriService): void
    {
        $agriService->verifyProduceUpdate($this->trackingId);
    }
}
