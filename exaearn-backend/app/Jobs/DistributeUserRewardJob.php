<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\RewardEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DistributeUserRewardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $rewardId,
        public readonly string $walletAddress,
    ) {
    }

    public function handle(RewardEngineService $rewardEngine): void
    {
        $rewardEngine->distributeReward($this->userId, $this->rewardId, $this->walletAddress);
    }
}
