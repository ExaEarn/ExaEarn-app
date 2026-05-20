<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\RewardEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateRewardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $activityType,
        public readonly string $activityValue,
        public readonly array $context = [],
    ) {
    }

    public function handle(RewardEngineService $rewardEngine): void
    {
        $rewardEngine->issueReward(
            $this->userId,
            $this->activityType,
            $this->activityValue,
            $this->context
        );
    }
}
