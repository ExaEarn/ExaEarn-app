<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Services\StakingLedgerService;
use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MonitorDelegationConfirmation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function handle(StakingProviderRegistry $providers, StakingLedgerService $ledger): void
    {
        $batches = DB::table('staking_delegation_batches')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_delegation_batches.staking_asset_id')
            ->where('staking_delegation_batches.status', 'delegation_submitted')
            ->whereNotNull('staking_delegation_batches.blockchain_transaction_hash')
            ->select('staking_delegation_batches.*', 'staking_assets.symbol')
            ->limit(100)
            ->get();

        foreach ($batches as $batch) {
            try {
                $confirmation = $providers
                    ->forSymbol((string) $batch->symbol)
                    ->monitorConfirmation((string) $batch->blockchain_transaction_hash);

                $status = (string) ($confirmation['status'] ?? 'pending');
                if (in_array($status, ['confirmed', 'finalized'], true)) {
                    DB::table('staking_delegation_batches')->where('id', $batch->id)->update([
                        'status' => 'confirmed',
                        'confirmed_at' => now(),
                        'metadata' => json_encode(array_merge($this->metadata($batch), ['confirmation' => $confirmation])),
                        'updated_at' => now(),
                    ]);
                    DB::table('staking_transactions')
                        ->where('idempotency_key', "staking:delegation-submitted:{$batch->id}")
                        ->update([
                            'status' => 'confirmed',
                            'blockchain_block_or_slot' => $confirmation['block_or_slot'] ?? null,
                            'confirmation_count' => $confirmation['confirmation_count'] ?? null,
                            'processed_at' => now(),
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                if (in_array($status, ['failed', 'rejected', 'dropped'], true)) {
                    $this->failBatch($batch, $ledger, $confirmation['message'] ?? 'Delegation transaction failed permanently.');
                }
            } catch (Throwable $exception) {
                $this->audit((int) $batch->staking_asset_id, (int) $batch->id, 'delegation_confirmation_blocked', [
                    'message' => $exception->getMessage(),
                    'transaction_hash' => $batch->blockchain_transaction_hash,
                ]);
            }
        }
    }

    public function uniqueId(): string
    {
        return 'staking:monitor-delegation-confirmation';
    }

    private function failBatch(object $batch, StakingLedgerService $ledger, string $reason): void
    {
        DB::transaction(function () use ($batch, $ledger, $reason): void {
            $allocations = DB::table('staking_delegation_allocations')
                ->where('staking_delegation_batch_id', $batch->id)
                ->get();

            foreach ($allocations as $allocation) {
                $position = DB::table('staking_positions')->where('id', $allocation->staking_position_id)->lockForUpdate()->first();
                if (! $position || $position->status === 'failed') {
                    continue;
                }

                $ledger->reversePendingStakeReservation((int) $position->id, (string) $allocation->allocated_amount, "failed-batch:{$batch->id}:allocation:{$allocation->id}");

                DB::table('staking_positions')->where('id', $position->id)->update([
                    'pending_stake_amount' => '0',
                    'status' => 'failed',
                    'metadata' => json_encode(array_merge($this->metadata($position), ['failure_reason' => $reason])),
                    'updated_at' => now(),
                ]);
                DB::table('staking_delegation_allocations')->where('id', $allocation->id)->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);
            }

            DB::table('staking_delegation_batches')->where('id', $batch->id)->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'updated_at' => now(),
            ]);
            DB::table('staking_transactions')
                ->where('idempotency_key', "staking:delegation-submitted:{$batch->id}")
                ->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                    'updated_at' => now(),
                ]);
        });
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
}
