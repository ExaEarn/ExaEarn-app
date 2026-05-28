<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ReferralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessReferralRewardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $referredUserId,
        public readonly string $activityType,
        public readonly array $metadata = [],
    ) {
    }

    public function handle(ReferralService $referralService): void
    {
        $referralService->processQualifiedActivity(
            $this->referredUserId,
            $this->activityType,
            $this->metadata
        );
    }
}
