<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Models\UserStake;
use App\Services\StakingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculateStakingRewardsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $stakeId)
    {
    }

    public function handle(StakingService $stakingService): void
    {
        $stake = UserStake::query()->find($this->stakeId);
        if (!$stake) {
            return;
        }

        $stakingService->syncStakeRewards($stake);
    }
}
