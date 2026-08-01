<?php

declare(strict_types=1);

namespace App\Domain\Staking\Services;

use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StakingLedgerService
{
    private const SCALE = 18;

    public function __construct(private readonly LedgerService $ledger) {}

    public function movePendingPrincipalToActive(int $positionId, string $amount, string $referenceSuffix): void
    {
        $this->moveUserPrincipal(
            $positionId,
            $amount,
            'staking_pending',
            'staking_active',
            'staking:activate',
            'Activate verified staked principal',
            $referenceSuffix
        );
    }

    public function reserveActivePrincipalForUnstaking(int $positionId, string $amount, string $referenceSuffix): void
    {
        $this->moveUserPrincipal(
            $positionId,
            $amount,
            'staking_active',
            'staking_pending_unstake',
            'staking:reserve-unstake',
            'Reserve active principal for network unstaking',
            $referenceSuffix
        );
    }

    public function reversePendingStakeReservation(int $positionId, string $amount, string $referenceSuffix): void
    {
        $this->moveUserPrincipal(
            $positionId,
            $amount,
            'staking_pending',
            'funding',
            'staking:reverse-pending',
            'Reverse failed staking reservation',
            $referenceSuffix
        );
    }

    public function releaseUnstakedPrincipal(int $unstakeRequestId): void
    {
        DB::transaction(function () use ($unstakeRequestId): void {
            $request = DB::table('staking_unstake_requests')
                ->join('staking_positions', 'staking_positions.id', '=', 'staking_unstake_requests.staking_position_id')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')
                ->where('staking_unstake_requests.id', $unstakeRequestId)
                ->select(
                    'staking_unstake_requests.*',
                    'staking_positions.id as position_id',
                    'staking_positions.user_id',
                    'staking_positions.pending_unstake_amount',
                    'staking_positions.active_principal_amount',
                    'staking_positions.status as position_status',
                    'staking_assets.symbol'
                )
                ->lockForUpdate()
                ->first();

            if (! $request) {
                throw new RuntimeException('Unstake request not found.');
            }

            if (! in_array($request->status, ['withdrawable', 'principal_withdrawn'], true)) {
                throw new RuntimeException('Principal cannot be released before withdrawable confirmation.');
            }

            if ($request->principal_released_at !== null || $request->status === 'released') {
                return;
            }

            $amount = $this->normalize((string) $request->requested_amount);
            $from = $this->ledger->getOrCreateAccount((int) $request->user_id, 'staking_pending_unstake', (string) $request->symbol);
            $to = $this->ledger->getOrCreateAccount((int) $request->user_id, 'funding', (string) $request->symbol);
            $reference = "staking:principal-release:{$request->id}";

            $ledgerTx = $this->ledger->postDoubleEntry($reference, 'Release verified unstaked principal', [
                ['account_id' => $from->id, 'amount' => $this->sub('0', $amount), 'asset' => (string) $request->symbol, 'user_id' => (int) $request->user_id],
                ['account_id' => $to->id, 'amount' => $amount, 'asset' => (string) $request->symbol, 'user_id' => (int) $request->user_id],
            ], 'staking_principal_release');

            DB::table('staking_unstake_requests')->where('id', $request->id)->update([
                'status' => 'released',
                'principal_released_at' => now(),
                'updated_at' => now(),
            ]);

            $pendingUnstake = $this->maxZero($this->sub((string) $request->pending_unstake_amount, $amount));
            $activePrincipal = (string) $request->active_principal_amount;
            DB::table('staking_positions')->where('id', $request->position_id)->update([
                'pending_unstake_amount' => $pendingUnstake,
                'status' => $this->compare($activePrincipal, '0') <= 0 && $this->compare($pendingUnstake, '0') <= 0 ? 'completed' : 'active',
                'completed_at' => $this->compare($activePrincipal, '0') <= 0 && $this->compare($pendingUnstake, '0') <= 0 ? now() : null,
                'updated_at' => now(),
            ]);

            DB::table('staking_transactions')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'user_id' => (int) $request->user_id,
                'staking_position_id' => (int) $request->position_id,
                'staking_asset_id' => DB::table('staking_assets')->where('symbol', (string) $request->symbol)->value('id'),
                'transaction_type' => 'principal_release',
                'amount' => $amount,
                'fee_amount' => '0',
                'net_amount' => $amount,
                'ledger_transaction_id' => $ledgerTx->id,
                'status' => 'completed',
                'idempotency_key' => $reference,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function distributeNativeRewardAllocation(int $allocationId): void
    {
        DB::transaction(function () use ($allocationId): void {
            $allocation = DB::table('staking_reward_allocations')
                ->join('staking_reward_batches', 'staking_reward_batches.id', '=', 'staking_reward_allocations.staking_reward_batch_id')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_reward_batches.staking_asset_id')
                ->where('staking_reward_allocations.id', $allocationId)
                ->select('staking_reward_allocations.*', 'staking_reward_batches.status as batch_status', 'staking_assets.symbol', 'staking_reward_batches.staking_asset_id')
                ->lockForUpdate()
                ->first();

            if (! $allocation) {
                throw new RuntimeException('Reward allocation not found.');
            }
            if ($allocation->status === 'distributed') {
                return;
            }
            if ($allocation->batch_status !== 'approved') {
                throw new RuntimeException('Reward batch must be approved before distribution.');
            }

            $netReward = $this->normalize((string) $allocation->net_native_reward);
            $platformFee = $this->normalize((string) $allocation->platform_fee);
            $symbol = (string) $allocation->symbol;
            $entries = [];
            $clearingDebit = $this->add($netReward, $platformFee);
            $clearing = $this->ledger->getOrCreateAccount(null, 'native_staking_rewards_clearing', $symbol);
            $userPayable = $this->ledger->getOrCreateAccount((int) $allocation->user_id, 'staking_reward_payable', $symbol);
            $entries[] = ['account_id' => $clearing->id, 'amount' => $this->sub('0', $clearingDebit), 'asset' => $symbol];
            $entries[] = ['account_id' => $userPayable->id, 'amount' => $netReward, 'asset' => $symbol, 'user_id' => (int) $allocation->user_id];

            if ($this->compare($platformFee, '0') > 0) {
                $commission = $this->ledger->getOrCreateAccount(null, 'staking_commission_revenue', $symbol);
                $entries[] = ['account_id' => $commission->id, 'amount' => $platformFee, 'asset' => $symbol];
            }

            $reference = "staking:native-reward-distribution:{$allocation->id}";
            $ledgerTx = $this->ledger->postDoubleEntry($reference, 'Distribute approved native staking reward', $entries, 'staking_reward_distribution');

            DB::table('staking_reward_allocations')->where('id', $allocation->id)->update([
                'status' => 'distributed',
                'native_reward_ledger_transaction_id' => $ledgerTx->id,
                'distributed_at' => now(),
                'updated_at' => now(),
            ]);

            $position = DB::table('staking_positions')->where('id', $allocation->staking_position_id)->lockForUpdate()->first();
            DB::table('staking_positions')->where('id', $allocation->staking_position_id)->update([
                'total_native_gross_rewards' => $this->add((string) $position->total_native_gross_rewards, (string) $allocation->gross_native_reward),
                'total_native_validator_fees' => $this->add((string) $position->total_native_validator_fees, (string) $allocation->validator_fee_share),
                'total_native_network_fees' => $this->add((string) $position->total_native_network_fees, (string) $allocation->network_fee_share),
                'total_native_platform_fees' => $this->add((string) $position->total_native_platform_fees, (string) $allocation->platform_fee),
                'total_native_net_rewards' => $this->add((string) $position->total_native_net_rewards, (string) $allocation->net_native_reward),
                'updated_at' => now(),
            ]);

            DB::table('staking_transactions')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'user_id' => (int) $allocation->user_id,
                'staking_position_id' => (int) $allocation->staking_position_id,
                'staking_asset_id' => (int) $allocation->staking_asset_id,
                'transaction_type' => 'native_reward_distribution',
                'amount' => (string) $allocation->gross_native_reward,
                'fee_amount' => $this->add((string) $allocation->validator_fee_share, $this->add((string) $allocation->network_fee_share, (string) $allocation->platform_fee)),
                'net_amount' => $netReward,
                'ledger_transaction_id' => $ledgerTx->id,
                'status' => 'completed',
                'idempotency_key' => $reference,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function moveUserPrincipal(int $positionId, string $amount, string $fromType, string $toType, string $referencePrefix, string $description, string $referenceSuffix): void
    {
        DB::transaction(function () use ($positionId, $amount, $fromType, $toType, $referencePrefix, $description, $referenceSuffix): void {
            $position = DB::table('staking_positions')
                ->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')
                ->where('staking_positions.id', $positionId)
                ->select('staking_positions.*', 'staking_assets.symbol')
                ->lockForUpdate()
                ->first();

            if (! $position) {
                throw new RuntimeException('Staking position not found.');
            }

            $normalized = $this->normalize($amount);
            $from = $this->ledger->getOrCreateAccount((int) $position->user_id, $fromType, (string) $position->symbol);
            $to = $this->ledger->getOrCreateAccount((int) $position->user_id, $toType, (string) $position->symbol);
            $reference = "{$referencePrefix}:{$position->id}:{$referenceSuffix}";

            $this->ledger->postDoubleEntry($reference, $description, [
                ['account_id' => $from->id, 'amount' => $this->sub('0', $normalized), 'asset' => (string) $position->symbol, 'user_id' => (int) $position->user_id],
                ['account_id' => $to->id, 'amount' => $normalized, 'asset' => (string) $position->symbol, 'user_id' => (int) $position->user_id],
            ], 'staking_principal_transfer');
        });
    }

    private function normalize(string $amount): string
    {
        return function_exists('bcadd') ? bcadd($amount, '0', self::SCALE) : number_format((float) $amount, self::SCALE, '.', '');
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function maxZero(string $amount): string
    {
        return $this->compare($amount, '0') < 0 ? '0' : $amount;
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        return (float) $a <=> (float) $b;
    }
}
