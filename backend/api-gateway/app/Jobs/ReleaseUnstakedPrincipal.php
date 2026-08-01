<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingLedgerService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReleaseUnstakedPrincipal implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(StakingLedgerService $ledger): void
    {
        $requests = DB::table('staking_unstake_requests')
            ->whereIn('status', ['withdrawable', 'principal_withdrawn'])
            ->whereNull('principal_released_at')
            ->limit(100)
            ->get();

        foreach ($requests as $request) {
            $ledger->releaseUnstakedPrincipal((int) $request->id);
        }
    }

    public function uniqueId(): string
    {
        return 'staking:release-unstaked-principal';
    }
}
