<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Jobs\CalculateRewardJob;
use App\Models\StakingPool;
use App\Models\StakingReward;
use App\Models\UserStake;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StakingService
{
    private const SCALE = 8;

    public function __construct(
        private readonly BlockchainService $blockchain,
        private readonly TransactionService $transactions,
        private readonly ReferralService $referrals,
    ) {
    }

    public function listPools(): Collection
    {
        return StakingPool::query()->orderBy('lock_period')->get();
    }

    public function userStakes(int $userId): Collection
    {
        return UserStake::query()
            ->with('pool')
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(function (UserStake $stake) {
                $this->syncStakeRewards($stake);
                $stake->refresh();

                return [
                    'id' => $stake->id,
                    'pool' => $stake->pool,
                    'amount' => (string) $stake->amount,
                    'compounded_amount' => (string) $stake->compounded_amount,
                    'pending_reward' => $this->calculatePendingReward($stake),
                    'lock_start' => $stake->lock_start,
                    'lock_end' => $stake->lock_end,
                    'auto_compound' => $stake->auto_compound,
                    'status' => $stake->status,
                    'tx_hash' => $stake->tx_hash,
                    'unstake_tx_hash' => $stake->unstake_tx_hash,
                ];
            });
    }

    public function createPool(array $payload): StakingPool
    {
        return StakingPool::query()->create([
            'asset' => strtoupper((string) $payload['asset']),
            'reward_token' => strtoupper((string) ($payload['reward_token'] ?? config('staking.reward_token'))),
            'contract_pool_id' => $payload['contract_pool_id'] ?? null,
            'lock_period' => (int) $payload['lock_period'],
            'reward_rate' => (string) $payload['reward_rate'],
            'reward_multiplier' => (string) ($payload['reward_multiplier'] ?? '1'),
            'pool_size' => (string) $payload['pool_size'],
            'total_staked' => '0',
            'status' => $payload['status'] ?? 'active',
            'metadata' => $payload['metadata'] ?? null,
        ]);
    }

    public function stake(int $userId, int $poolId, string $amount, bool $autoCompound = false): array
    {
        $pool = StakingPool::query()->findOrFail($poolId);
        if ($pool->status !== 'active') {
            throw new RuntimeException('Staking pool is not active.');
        }

        if ($this->compare($this->add((string) $pool->total_staked, $amount), (string) $pool->pool_size) > 0) {
            throw new RuntimeException('Staking pool allocation exceeded.');
        }

        $result = DB::transaction(function () use ($userId, $pool, $amount, $autoCompound) {
            $transaction = $this->transactions->recordLockedOperation(
                $userId,
                TransactionType::StakingLock,
                (string) $pool->asset,
                $amount,
                "stake:pool:{$pool->id}",
                ['pool_id' => $pool->id, 'auto_compound' => $autoCompound]
            );

            $stake = UserStake::query()->create([
                'user_id' => $userId,
                'pool_id' => $pool->id,
                'amount' => $amount,
                'compounded_amount' => '0',
                'lock_start' => now(),
                'lock_end' => now()->addSeconds($pool->lock_period),
                'last_reward_at' => now(),
                'auto_compound' => $autoCompound,
                'status' => 'active',
                'metadata' => ['transaction_id' => $transaction->transaction_id],
            ]);

            $onChain = $this->blockchain->stakeIntoPool(
                $userId,
                (int) ($pool->contract_pool_id ?? $pool->id),
                $amount,
                $autoCompound
            );

            $stake->tx_hash = $onChain['tx_hash'] ?? null;
            $stake->save();

            $pool->total_staked = $this->add((string) $pool->total_staked, $amount);
            $pool->save();

            return ['stake' => $stake->fresh('pool'), 'blockchain' => $onChain];
        });

        $this->referrals->queueQualifiedActivity($userId, 'staking_participation', [
            'event_key' => 'stake:' . $result['stake']->id,
            'stake_id' => $result['stake']->id,
            'ip_address' => request()?->ip(),
        ]);

        CalculateRewardJob::dispatch($userId, 'staking_participation', $amount, [
            'activity_key' => 'stake:' . $result['stake']->id,
            'stake_id' => $result['stake']->id,
            'ip_address' => request()?->ip(),
        ])->onQueue('rewards');

        return $result;
    }

    public function syncStakeRewards(UserStake $stake): ?StakingReward
    {
        $stake->loadMissing('pool');
        if ($stake->status !== 'active') {
            return null;
        }

        $rewardAmount = $this->calculatePendingReward($stake);
        if ($this->compare($rewardAmount, (string) config('staking.min_reward_payout', '0.00000001')) < 0) {
            return null;
        }

        $reward = StakingReward::query()->create([
            'user_id' => $stake->user_id,
            'stake_id' => $stake->id,
            'reward_amount' => $rewardAmount,
            'reward_token' => (string) $stake->pool->reward_token,
            'claimed' => false,
            'metadata' => ['generated_at' => now()->toISOString()],
        ]);

        $stake->last_reward_at = now();
        $stake->save();

        if ($stake->auto_compound) {
            $this->compoundStakeRewards($stake->user_id, $stake->id, false);
            $stake->refresh();
        }

        return $reward;
    }

    public function claimStakeRewards(int $userId, int $stakeId): array
    {
        $stake = UserStake::query()->with('pool')->where('user_id', $userId)->findOrFail($stakeId);
        $this->syncStakeRewards($stake);
        $rewards = StakingReward::query()
            ->where('stake_id', $stake->id)
            ->where('claimed', false)
            ->get();

        $total = $rewards->reduce(fn (string $carry, StakingReward $reward) => $this->add($carry, (string) $reward->reward_amount), '0');
        if ($this->compare($total, '0') <= 0) {
            throw new RuntimeException('No claimable staking rewards available.');
        }

        $blockchain = $this->blockchain->claimStakingRewards($userId, (int) ($stake->pool->contract_pool_id ?? $stake->pool->id));
        $transaction = $this->transactions->recordReward(
            $userId,
            TransactionType::StakingReward,
            (string) $stake->pool->reward_token,
            $total,
            "stake_reward:{$stake->id}",
            ['stake_id' => $stake->id, 'tx_hash' => $blockchain['tx_hash'] ?? null]
        );

        foreach ($rewards as $reward) {
            $reward->claimed = true;
            $reward->claimed_at = now();
            $reward->tx_hash = $blockchain['tx_hash'] ?? null;
            $reward->metadata = array_merge($reward->metadata ?? [], ['transaction_id' => $transaction->transaction_id]);
            $reward->save();
        }

        return ['amount' => $total, 'transaction' => $transaction, 'blockchain' => $blockchain];
    }

    public function compoundStakeRewards(int $userId, int $stakeId, bool $syncFirst = true): array
    {
        $stake = UserStake::query()->with('pool')->where('user_id', $userId)->findOrFail($stakeId);
        if ($syncFirst) {
            $this->syncStakeRewards($stake);
        }

        $rewards = StakingReward::query()
            ->where('stake_id', $stake->id)
            ->where('claimed', false)
            ->get();

        $total = $rewards->reduce(fn (string $carry, StakingReward $reward) => $this->add($carry, (string) $reward->reward_amount), '0');
        if ($this->compare($total, '0') <= 0) {
            throw new RuntimeException('No rewards available to compound.');
        }

        $blockchain = $this->blockchain->compoundStakingRewards($userId, (int) ($stake->pool->contract_pool_id ?? $stake->pool->id));

        $stake->compounded_amount = $this->add((string) $stake->compounded_amount, $total);
        $stake->auto_compound = true;
        $stake->save();

        foreach ($rewards as $reward) {
            $reward->claimed = true;
            $reward->claimed_at = now();
            $reward->tx_hash = $blockchain['tx_hash'] ?? null;
            $reward->metadata = array_merge($reward->metadata ?? [], ['compounded' => true]);
            $reward->save();
        }

        return ['amount' => $total, 'stake' => $stake->fresh(), 'blockchain' => $blockchain];
    }

    public function unstake(int $userId, int $stakeId, ?string $amount = null): array
    {
        $stake = UserStake::query()->with('pool')->where('user_id', $userId)->findOrFail($stakeId);
        if ($stake->status !== 'active') {
            throw new RuntimeException('Stake is not active.');
        }
        if (now()->lt($stake->lock_end)) {
            throw new RuntimeException('Stake is still locked.');
        }

        $unstakeAmount = $amount ?? (string) $stake->amount;
        if ($this->compare($unstakeAmount, '0') <= 0 || $this->compare($unstakeAmount, (string) $stake->amount) > 0) {
            throw new RuntimeException('Invalid unstake amount.');
        }

        $this->syncStakeRewards($stake);
        $blockchain = $this->blockchain->unstakeFromPool($userId, (int) ($stake->pool->contract_pool_id ?? $stake->pool->id), $unstakeAmount);

        $this->transactions->releaseLockedFunds(
            $userId,
            TransactionType::StakingUnlock,
            (string) $stake->pool->asset,
            $unstakeAmount,
            "unstake:{$stake->id}",
            ['stake_id' => $stake->id, 'tx_hash' => $blockchain['tx_hash'] ?? null]
        );

        $claimableCompounded = (string) $stake->compounded_amount;
        if ($this->compare($claimableCompounded, '0') > 0) {
            $this->transactions->recordReward(
                $userId,
                TransactionType::StakingReward,
                (string) $stake->pool->reward_token,
                $claimableCompounded,
                "stake_compound_release:{$stake->id}",
                ['stake_id' => $stake->id, 'tx_hash' => $blockchain['tx_hash'] ?? null]
            );
        }

        $stake->amount = $this->sub((string) $stake->amount, $unstakeAmount);
        $stake->compounded_amount = '0';
        $stake->unstake_tx_hash = $blockchain['tx_hash'] ?? null;
        $stake->status = $this->compare((string) $stake->amount, '0') <= 0 ? 'completed' : 'active';
        $stake->save();

        $stake->pool->total_staked = $this->sub((string) $stake->pool->total_staked, $unstakeAmount);
        $stake->pool->save();

        return ['stake' => $stake->fresh('pool'), 'blockchain' => $blockchain];
    }

    public function calculatePendingReward(UserStake $stake): string
    {
        $stake->loadMissing('pool');
        if ($stake->status !== 'active') {
            return '0';
        }

        $lastRewardAt = $stake->last_reward_at ?? $stake->lock_start;
        $elapsed = max(0, now()->timestamp - $lastRewardAt->timestamp);
        if ($elapsed === 0) {
            return '0';
        }

        $effectiveStake = $this->add((string) $stake->amount, (string) $stake->compounded_amount);
        $baseReward = $this->mul($effectiveStake, (string) $stake->pool->reward_rate);
        $annualized = $this->div($baseReward, '100');
        $withMultiplier = $this->mul($annualized, (string) $stake->pool->reward_multiplier);
        $perSecond = $this->div($withMultiplier, (string) config('staking.seconds_per_year', 31536000));

        return $this->mul($perSecond, (string) $elapsed);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, self::SCALE) : number_format((float) $a * (float) $b, self::SCALE, '.', '');
    }

    private function div(string $a, string $b): string
    {
        if ($this->compare($b, '0') === 0) {
            throw new RuntimeException('Division by zero.');
        }

        return function_exists('bcdiv') ? bcdiv($a, $b, self::SCALE) : number_format((float) $a / (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        $fa = (float) $a;
        $fb = (float) $b;
        return $fa < $fb ? -1 : ($fa > $fb ? 1 : 0);
    }
}
