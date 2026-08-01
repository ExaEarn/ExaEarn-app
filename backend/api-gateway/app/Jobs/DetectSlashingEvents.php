<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DetectSlashingEvents implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 900;

    public function handle(StakingProviderRegistry $providers): void
    {
        $delegations = DB::table('staking_delegations')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_delegations.staking_asset_id')
            ->whereIn('staking_delegations.status', ['active', 'delegated'])
            ->select('staking_delegations.*', 'staking_assets.symbol')
            ->get();

        foreach ($delegations as $delegation) {
            try {
                $event = $providers->forSymbol((string) $delegation->symbol)->detectSlashing((array) $delegation);
                if (($event['slashed'] ?? false) !== true) {
                    continue;
                }

                DB::table('staking_slashing_events')->insert([
                    'staking_asset_id' => $delegation->staking_asset_id,
                    'subject_type' => 'staking_delegation',
                    'subject_id' => $delegation->id,
                    'status' => 'detected',
                    'amount' => (string) ($event['amount'] ?? '0'),
                    'reference' => (string) Str::uuid(),
                    'metadata' => json_encode($event),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    public function uniqueId(): string
    {
        return 'staking:detect-slashing';
    }
}
