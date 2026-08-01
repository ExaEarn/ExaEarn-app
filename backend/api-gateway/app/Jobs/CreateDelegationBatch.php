<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Staking\Contracts\SecureSignerInterface;
use App\Domain\Staking\Services\StakingProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateDelegationBatch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(StakingProviderRegistry $providers, SecureSignerInterface $signer): void
    {
        $groups = DB::table('staking_positions')
            ->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')
            ->join('staking_products', 'staking_products.id', '=', 'staking_positions.staking_product_id')
            ->where('staking_positions.status', 'batching')
            ->where('staking_assets.emergency_paused', false)
            ->where('staking_assets.native_staking_enabled', true)
            ->where('staking_assets.new_positions_enabled', true)
            ->select(
                'staking_positions.staking_asset_id',
                'staking_products.network_environment',
                'staking_assets.symbol',
                'staking_assets.network',
            )
            ->groupBy('staking_positions.staking_asset_id', 'staking_products.network_environment', 'staking_assets.symbol', 'staking_assets.network')
            ->limit(20)
            ->get();

        foreach ($groups as $group) {
            try {
                $this->createAndSubmitForGroup($group, $providers, $signer);
            } catch (Throwable $exception) {
                $this->audit(
                    (int) $group->staking_asset_id,
                    'delegation_batch_blocked',
                    [
                        'symbol' => $group->symbol,
                        'network_environment' => $group->network_environment,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }
    }

    public function uniqueId(): string
    {
        return 'staking:create-delegation-batch';
    }

    private function createAndSubmitForGroup(object $group, StakingProviderRegistry $providers, SecureSignerInterface $signer): void
    {
        $provider = $providers->forSymbol((string) $group->symbol);
        $health = $provider->healthCheck();
        if (($health['ready'] ?? false) !== true) {
            throw new RuntimeException('Provider is not ready for delegation.');
        }

        $validator = $this->selectValidator((int) $group->staking_asset_id);
        $wallet = $this->selectWallet((int) $group->staking_asset_id, (string) $group->network_environment);
        $this->assertWalletReady($wallet);

        [$batchId, $totalAmount] = DB::transaction(function () use ($group, $validator, $wallet): array {
            $positions = $this->lockBatchPositions((int) $group->staking_asset_id, (string) $group->network_environment);
            if ($positions->isEmpty()) {
                return [null, '0'];
            }

            $totalAmount = $positions->reduce(fn (string $carry, object $position): string => $this->add($carry, (string) $position->pending_stake_amount), '0');
            $this->assertValidatorCapacity($validator, $totalAmount);

            $batchId = DB::table('staking_delegation_batches')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'staking_asset_id' => (int) $group->staking_asset_id,
                'validator_id' => (int) $validator->id,
                'staking_wallet_id' => (int) $wallet->id,
                'network_environment' => (string) $group->network_environment,
                'total_amount' => $totalAmount,
                'position_count' => $positions->count(),
                'status' => 'awaiting_signature',
                'idempotency_key' => 'staking:delegation-batch:'.Str::uuid(),
                'metadata' => json_encode([
                    'validator' => $validator->provider_identifier,
                    'wallet' => $wallet->wallet_address,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($positions as $position) {
                DB::table('staking_delegation_allocations')->insert([
                    'staking_delegation_batch_id' => $batchId,
                    'staking_position_id' => $position->id,
                    'allocated_amount' => (string) $position->pending_stake_amount,
                    'status' => 'allocated',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('staking_positions')->where('id', $position->id)->update([
                    'status' => 'awaiting_signature',
                    'updated_at' => now(),
                ]);
            }

            return [$batchId, $totalAmount];
        });

        if ($batchId === null) {
            return;
        }

        try {
            $batch = DB::table('staking_delegation_batches')->where('id', $batchId)->first();
            $unsignedPayload = $provider->buildDelegationTransaction([
                'batch' => (array) $batch,
                'validator' => (array) $validator,
                'wallet' => (array) $wallet,
                'amount' => $totalAmount,
            ]);
            $unsignedReference = (string) ($unsignedPayload['payload_reference'] ?? hash('sha256', json_encode($unsignedPayload, JSON_THROW_ON_ERROR)));
            $signature = $signer->requestSignature((string) $group->symbol, $provider->network(), $unsignedPayload, (string) $batch->idempotency_key);
            $submission = $provider->submitSignedTransaction((string) $signature['signed_payload'], [
                'batch_id' => $batchId,
                'signing_reference' => $signature['signing_reference'] ?? null,
            ]);
            $transactionHash = (string) ($submission['transaction_hash'] ?? '');
            if ($transactionHash === '') {
                throw new RuntimeException('Provider did not return a blockchain transaction hash.');
            }

            DB::transaction(function () use ($batchId, $group, $validator, $wallet, $totalAmount, $unsignedReference, $signature, $transactionHash): void {
                $delegationId = DB::table('staking_delegations')->insertGetId([
                    'staking_asset_id' => (int) $group->staking_asset_id,
                    'staking_validator_id' => (int) $validator->id,
                    'staking_wallet_id' => (int) $wallet->id,
                    'provider_reference' => "delegation-batch:{$batchId}",
                    'delegated_amount' => $totalAmount,
                    'active_amount' => '0',
                    'blockchain_transaction_hash' => $transactionHash,
                    'status' => 'delegation_submitted',
                    'delegated_at' => now(),
                    'metadata' => json_encode(['batch_id' => $batchId]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('staking_delegation_batches')->where('id', $batchId)->update([
                    'status' => 'delegation_submitted',
                    'unsigned_payload_reference' => $unsignedReference,
                    'signing_request_reference' => (string) ($signature['signing_reference'] ?? ''),
                    'blockchain_transaction_hash' => $transactionHash,
                    'submitted_at' => now(),
                    'metadata' => json_encode([
                        'staking_delegation_id' => $delegationId,
                        'signing_status' => $signature['status'] ?? 'signed',
                    ]),
                    'updated_at' => now(),
                ]);

                DB::table('staking_positions')
                    ->whereIn('id', DB::table('staking_delegation_allocations')->where('staking_delegation_batch_id', $batchId)->pluck('staking_position_id'))
                    ->update([
                        'status' => 'delegation_submitted',
                        'delegation_submitted_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('staking_transactions')->insert([
                    'public_id' => (string) Str::uuid(),
                    'staking_asset_id' => (int) $group->staking_asset_id,
                    'staking_delegation_id' => $delegationId,
                    'transaction_type' => 'delegation',
                    'amount' => $totalAmount,
                    'fee_amount' => '0',
                    'net_amount' => $totalAmount,
                    'blockchain_transaction_hash' => $transactionHash,
                    'status' => 'submitted',
                    'idempotency_key' => "staking:delegation-submitted:{$batchId}",
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            DB::table('staking_delegation_batches')->where('id', $batchId)->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
                'updated_at' => now(),
            ]);
            DB::table('staking_positions')
                ->whereIn('id', DB::table('staking_delegation_allocations')->where('staking_delegation_batch_id', $batchId)->pluck('staking_position_id'))
                ->update([
                    'status' => 'batching',
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    private function selectValidator(int $assetId): object
    {
        $validator = DB::table('staking_validators')
            ->where('staking_asset_id', $assetId)
            ->where('status', 'active')
            ->where('allowlisted', true)
            ->where('jailed_or_delinquent', false)
            ->orderByDesc('preferred')
            ->orderByDesc('performance_score')
            ->first();

        if (! $validator) {
            throw new RuntimeException('No active allowlisted validator is available.');
        }

        return $validator;
    }

    private function selectWallet(int $assetId, string $environment): object
    {
        $wallet = DB::table('staking_wallets')
            ->where('staking_asset_id', $assetId)
            ->where('network_environment', $environment)
            ->where('status', 'active')
            ->whereNotNull('secure_key_reference')
            ->orderBy('id')
            ->first();

        if (! $wallet) {
            throw new RuntimeException('No active staking wallet with secure signer key reference is available.');
        }

        return $wallet;
    }

    private function lockBatchPositions(int $assetId, string $environment): Collection
    {
        return DB::table('staking_positions')
            ->join('staking_products', 'staking_products.id', '=', 'staking_positions.staking_product_id')
            ->where('staking_positions.staking_asset_id', $assetId)
            ->where('staking_products.network_environment', $environment)
            ->where('staking_positions.status', 'batching')
            ->select('staking_positions.*')
            ->orderBy('staking_positions.id')
            ->limit(100)
            ->lockForUpdate()
            ->get();
    }

    private function assertValidatorCapacity(object $validator, string $totalAmount): void
    {
        if ($validator->delegation_capacity === null) {
            return;
        }

        $remaining = $this->sub((string) $validator->delegation_capacity, (string) $validator->delegated_amount);
        if ($this->compare($remaining, $totalAmount) < 0) {
            throw new RuntimeException('Validator capacity is insufficient for this delegation batch.');
        }
    }

    private function assertWalletReady(object $wallet): void
    {
        if ($this->compare((string) $wallet->fee_balance, '0') <= 0) {
            throw new RuntimeException('Staking wallet fee balance is insufficient.');
        }
    }

    private function audit(int $assetId, string $status, array $metadata): void
    {
        DB::table('staking_audit_logs')->insert([
            'staking_asset_id' => $assetId,
            'subject_type' => 'staking_delegation_batch',
            'status' => $status,
            'reference' => (string) Str::uuid(),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 18) : number_format((float) $a + (float) $b, 18, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 18) : number_format((float) $a - (float) $b, 18, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 18);
        }

        return (float) $a <=> (float) $b;
    }
}
