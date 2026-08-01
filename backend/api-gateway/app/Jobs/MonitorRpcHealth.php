<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class MonitorRpcHealth implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function handle(StakingProviderRegistry $providers): void
    {
        foreach (DB::table('staking_assets')->get() as $asset) {
            try {
                $health = $providers->forSymbol((string) $asset->symbol)->healthCheck();
            } catch (\Throwable $exception) {
                $health = ['ready' => false, 'status' => 'provider_missing', 'message' => $exception->getMessage()];
            }

            DB::table('staking_provider_health_checks')->insert([
                'staking_asset_id' => $asset->id,
                'status' => ($health['ready'] ?? false) ? 'healthy' : 'unhealthy',
                'reference' => strtolower((string) $asset->symbol).':provider:'.now()->timestamp,
                'metadata' => json_encode($health),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('staking_network_statuses')->updateOrInsert(
                ['staking_asset_id' => $asset->id],
                [
                    'status' => ($health['ready'] ?? false) ? 'online' : 'configuration_required',
                    'metadata' => json_encode($health),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function uniqueId(): string
    {
        return 'staking:monitor-rpc-health';
    }
}
