<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ListingLaunchEvent;
use App\Services\ListingLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteListingLaunchEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly int $eventId, public readonly string $claimToken) {}

    public function backoff(): array { return [30, 120, 600]; }

    public function handle(ListingLifecycleService $listings): void
    {
        $listings->executeDurableLaunchEvent($this->eventId, $this->claimToken);
    }

    public function failed(Throwable $exception): void
    {
        ListingLaunchEvent::query()->whereKey($this->eventId)
            ->where('claim_token', $this->claimToken)
            ->where('operation_status', ListingLaunchEvent::EXECUTING)
            ->update([
                'operation_status' => ListingLaunchEvent::FAILED, 'failure_code' => 'RETRY_EXHAUSTED',
                'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(), 'lease_expires_at' => null,
            ]);
    }
}
