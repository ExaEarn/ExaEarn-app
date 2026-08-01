<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingLedgerService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ActivateStakingPositions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(StakingLedgerService $ledger): void
    {
        $allocations = DB::table('staking_delegation_allocations')
            ->join('staking_delegation_batches', 'staking_delegation_batches.id', '=', 'staking_delegation_allocations.staking_delegation_batch_id')
            ->join('staking_positions', 'staking_positions.id', '=', 'staking_delegation_allocations.staking_position_id')
            ->where('staking_delegation_batches.status', 'activated')
            ->whereIn('staking_delegation_allocations.status', ['allocated', 'confirmed'])
            ->whereIn('staking_positions.status', ['batching', 'delegation_submitted', 'awaiting_activation'])
            ->select('staking_delegation_allocations.*')
            ->limit(100)
            ->get();

        foreach ($allocations as $allocation) {
            DB::transaction(function () use ($allocation, $ledger): void {
                $position = DB::table('staking_positions')->where('id', $allocation->staking_position_id)->lockForUpdate()->first();
                if (! $position || $position->status === 'active') {
                    return;
                }

                $amount = (string) $allocation->allocated_amount;
                $ledger->movePendingPrincipalToActive((int) $position->id, $amount, "allocation:{$allocation->id}");

                DB::table('staking_delegation_allocations')->where('id', $allocation->id)->update([
                    'activated_amount' => $amount,
                    'status' => 'activated',
                    'updated_at' => now(),
                ]);

                DB::table('staking_positions')->where('id', $position->id)->update([
                    'pending_stake_amount' => '0',
                    'active_principal_amount' => $amount,
                    'status' => 'active',
                    'activation_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function uniqueId(): string
    {
        return 'staking:activate-positions';
    }
}
