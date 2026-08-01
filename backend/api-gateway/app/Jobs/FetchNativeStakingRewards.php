<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchNativeStakingRewards implements ShouldBeUnique, ShouldQueue
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
                $provider = $providers->forSymbol((string) $delegation->symbol);
                $provider->discoverRewards((array) $delegation, ['from' => now()->subDay()->toISOString(), 'to' => now()->toISOString()]);
            } catch (\Throwable $exception) {
                DB::table('staking_audit_logs')->insert([
                    'staking_asset_id' => $delegation->staking_asset_id,
                    'subject_type' => 'staking_delegation',
                    'subject_id' => $delegation->id,
                    'status' => 'reward_fetch_blocked',
                    'reference' => (string) Str::uuid(),
                    'metadata' => json_encode(['message' => $exception->getMessage()]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function uniqueId(): string
    {
        return 'staking:fetch-native-rewards';
    }
}
