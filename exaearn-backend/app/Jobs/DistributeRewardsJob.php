<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Services\StakingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DistributeRewardsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $userId, private readonly int $stakeId)
    {
    }

    public function handle(StakingService $stakingService): void
    {
        $stakingService->claimStakeRewards($this->userId, $this->stakeId);
    }
}
