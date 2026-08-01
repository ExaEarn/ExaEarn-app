<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MonitorStakeActivation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function handle(StakingProviderRegistry $providers): void
    {
        $batches = DB::table('staking_delegation_batches')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_delegation_batches.staking_asset_id')
            ->where('staking_delegation_batches.status', 'confirmed')
            ->select(
                'staking_delegation_batches.*',
                'staking_assets.symbol'
            )
            ->limit(100)
            ->get();

        foreach ($batches as $batch) {
            try {
                $verification = $providers
                    ->forSymbol((string) $batch->symbol)
                    ->verifyDelegation((array) $batch);

                if (($verification['active'] ?? false) !== true) {
                    $this->audit((int) $batch->staking_asset_id, (int) $batch->id, 'delegation_not_active_yet', $verification);

                    continue;
                }

                $activeAmount = (string) ($verification['active_amount'] ?? $batch->total_amount);
                DB::transaction(function () use ($batch, $verification, $activeAmount): void {
                    $delegation = DB::table('staking_delegations')
                        ->where('provider_reference', "delegation-batch:{$batch->id}")
                        ->lockForUpdate()
                        ->first();

                    DB::table('staking_delegation_batches')->where('id', $batch->id)->update([
                        'status' => 'activated',
                        'activated_at' => now(),
                        'metadata' => json_encode(array_merge($this->metadata($batch), ['activation' => $verification])),
                        'updated_at' => now(),
                    ]);

                    if ($delegation !== null) {
                        DB::table('staking_delegations')->where('id', $delegation->id)->update([
                            'active_amount' => $activeAmount,
                            'status' => 'active',
                            'activated_at' => now(),
                            'metadata' => json_encode(['activation' => $verification]),
                            'updated_at' => now(),
                        ]);
                    }

                    $validator = DB::table('staking_validators')->where('id', $batch->validator_id)->lockForUpdate()->first();
                    if ($validator) {
                        DB::table('staking_validators')->where('id', $validator->id)->update([
                            'delegated_amount' => $this->add((string) $validator->delegated_amount, $activeAmount),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('staking_delegation_allocations')
                        ->where('staking_delegation_batch_id', $batch->id)
                        ->where('status', 'allocated')
                        ->update([
                            'status' => 'confirmed',
                            'updated_at' => now(),
                        ]);

                    DB::table('staking_positions')
                        ->whereIn('id', DB::table('staking_delegation_allocations')->where('staking_delegation_batch_id', $batch->id)->pluck('staking_position_id'))
                        ->where('status', 'delegation_submitted')
                        ->update([
                            'status' => 'awaiting_activation',
                            'updated_at' => now(),
                        ]);
                });
            } catch (Throwable $exception) {
                $this->audit((int) $batch->staking_asset_id, (int) $batch->id, 'stake_activation_check_blocked', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function uniqueId(): string
    {
        return 'staking:monitor-stake-activation';
    }

    private function audit(int $assetId, int $batchId, string $status, array $metadata): void
    {
        DB::table('staking_audit_logs')->insert([
            'staking_asset_id' => $assetId,
            'subject_type' => 'staking_delegation_batch',
            'subject_id' => $batchId,
            'status' => $status,
            'reference' => (string) Str::uuid(),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function metadata(object $row): array
    {
        $metadata = $row->metadata ?? null;
        if (! is_string($metadata) || $metadata === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 18) : number_format((float) $a + (float) $b, 18, '.', '');
    }
}
