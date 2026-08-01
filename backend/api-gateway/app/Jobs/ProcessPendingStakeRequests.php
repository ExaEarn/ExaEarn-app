<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessPendingStakeRequests implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(StakingProviderRegistry $providers): void
    {
        $positions = DB::table('staking_positions')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')
            ->where('staking_positions.status', 'pending')
            ->where('staking_assets.emergency_paused', false)
            ->select('staking_positions.*', 'staking_assets.symbol')
            ->limit(100)
            ->get();

        foreach ($positions as $position) {
            try {
                $health = $providers->forSymbol((string) $position->symbol)->healthCheck();
            } catch (\Throwable $exception) {
                $health = ['ready' => false, 'status' => 'provider_missing', 'message' => $exception->getMessage()];
            }

            if (($health['ready'] ?? false) !== true) {
                $this->audit((int) $position->staking_asset_id, (int) $position->user_id, (int) $position->id, 'delegation_blocked_provider_not_ready', $health);

                continue;
            }

            DB::table('staking_positions')->where('id', $position->id)->update([
                'status' => 'batching',
                'updated_at' => now(),
            ]);
        }
    }

    public function uniqueId(): string
    {
        return 'staking:process-pending';
    }

    private function audit(int $assetId, int $userId, int $positionId, string $status, array $metadata): void
    {
        DB::table('staking_audit_logs')->insert([
            'staking_asset_id' => $assetId,
            'user_id' => $userId,
            'subject_type' => 'staking_position',
            'subject_id' => $positionId,
            'status' => $status,
            'reference' => (string) Str::uuid(),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
