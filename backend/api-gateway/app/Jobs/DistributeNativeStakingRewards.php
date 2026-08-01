<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingLedgerService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DistributeNativeStakingRewards implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(StakingLedgerService $ledger): void
    {
        $allocations = DB::table('staking_reward_allocations')
            ->join('staking_reward_batches', 'staking_reward_batches.id', '=', 'staking_reward_allocations.staking_reward_batch_id')
            ->where('staking_reward_batches.status', 'approved')
            ->whereIn('staking_reward_allocations.status', ['approved', 'pending_distribution'])
            ->select('staking_reward_allocations.id')
            ->limit(200)
            ->get();

        foreach ($allocations as $allocation) {
            $ledger->distributeNativeRewardAllocation((int) $allocation->id);
        }
    }

    public function uniqueId(): string
    {
        return 'staking:distribute-native-rewards';
    }
}
