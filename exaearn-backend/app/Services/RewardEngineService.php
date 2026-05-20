<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RewardActivity;
use App\Models\User;
use App\Models\UserReward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class RewardEngineService
{
    public function __construct(
        private readonly RewardSecurityService $security,
        private readonly ExaPointRewardEngineService $exaPointRewards,
    ) {
    }

    public function syncActivities(): void
    {
        foreach ((array) config('rewards.activities', []) as $activityType => $definition) {
            RewardActivity::query()->updateOrCreate(
                ['activity_type' => $activityType],
                [
                    'reward_rate' => $definition['reward_rate'],
                    'daily_limit' => $definition['daily_limit'],
                    'status' => $definition['status'] ?? 'active',
                    'mode' => $definition['mode'] ?? 'formula',
                    'min_activity_value' => $definition['min_activity_value'] ?? '0',
                    'metadata' => $definition['metadata'] ?? null,
                ]
            );
        }
    }

    public function issueReward(int $userId, string $activityType, string $activityValue, array $context = []): UserReward
    {
        $this->syncActivities();

        $user = User::query()->findOrFail($userId);
        $activity = RewardActivity::query()->where('activity_type', $activityType)->firstOrFail();
        $activityKey = $this->resolveActivityKey($activityType, $context);

        if ($activity->status !== 'active') {
            throw new RuntimeException('Reward activity is disabled.');
        }

        if ((bool) config('rewards.require_email_verification', true) && !$user->email_verified_at) {
            throw new RuntimeException('Verified users only can receive rewards.');
        }

        if (UserReward::query()
            ->where('user_id', $userId)
            ->where('activity_type', $activityType)
            ->where('activity_key', $activityKey)
            ->exists()) {
            throw new RuntimeException('Duplicate reward activity detected.');
        }

        if ($this->compare($activityValue, (string) $activity->min_activity_value) < 0) {
            throw new RuntimeException('Activity value is below the reward threshold.');
        }

        $rewardAmount = isset($context['reward_amount_override'])
            ? (string) $context['reward_amount_override']
            : ($activity->mode === 'fixed'
                ? (string) $activity->reward_rate
                : $this->mul($activityValue, (string) $activity->reward_rate));

        $issuedToday = (string) UserReward::query()
            ->where('user_id', $userId)
            ->where('activity_type', $activityType)
            ->whereDate('created_at', today())
            ->whereNotIn('status', ['blocked', 'rejected'])
            ->sum('reward_amount');

        if ($this->compare($this->add($issuedToday, $rewardAmount), (string) $activity->daily_limit) > 0) {
            throw new RuntimeException('Daily reward cap exceeded.');
        }

        $flags = $this->security->inspect($user, $activityType, $context);
        $status = $flags === [] ? 'approved' : 'blocked';

        $pointsRewarded = '0';
        if ($status === 'approved') {
            $balance = $this->exaPointRewards->awardFromActivity($userId, $activityType, $rewardAmount, $context);
            $pointsRewarded = $balance['total_points'] ?? '0';
        }

        $reward = UserReward::query()->create([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'activity_value' => $activityValue,
            'reward_amount' => $rewardAmount,
            'reward_token' => 'EXAPOINT',
            'status' => $status === 'approved' ? 'claimed' : $status,
            'activity_key' => $activityKey,
            'validated_at' => now(),
            'distributed_at' => $status === 'approved' ? now() : null,
            'distribution_reference' => $status === 'approved' ? 'exapoint:auto' : null,
            'metadata' => array_merge($context, [
                'flags' => $flags,
                'reward_system' => 'exapoint',
                'points_rewarded_total' => $pointsRewarded,
            ]),
        ]);

        if ($flags !== []) {
            $this->security->suspend($user, $flags);
        }

        return $reward;
    }

    public function queueDistribution(int $userId, int $rewardId, string $walletAddress): UserReward
    {
        throw new RuntimeException('ExaToken reward distribution is disabled. Rewards are now issued as ExaPoints instantly.');
    }

    public function distributeReward(int $userId, int $rewardId, string $walletAddress): never
    {
        throw new RuntimeException('ExaToken reward distribution is disabled. Rewards are now issued as ExaPoints instantly.');
    }

    public function listRewards(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return UserReward::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function listActivities(): \Illuminate\Support\Collection
    {
        $this->syncActivities();

        return RewardActivity::query()->orderBy('activity_type')->get();
    }

    private function resolveActivityKey(string $activityType, array $context): string
    {
        return (string) ($context['activity_key']
            ?? $context['transaction_id']
            ?? $context['trade_uuid']
            ?? $context['stake_id']
            ?? $context['course_id']
            ?? ($activityType === 'daily_check_in' ? now()->toDateString() : $activityType . ':' . now()->timestamp));
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, 8);
        }

        return number_format((float) $left + (float) $right, 8, '.', '');
    }

    private function mul(string $left, string $right): string
    {
        if (function_exists('bcmul')) {
            return bcmul($left, $right, 8);
        }

        return number_format((float) $left * (float) $right, 8, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 8);
        }

        $leftFloat = (float) $left;
        $rightFloat = (float) $right;

        return $leftFloat < $rightFloat ? -1 : ($leftFloat > $rightFloat ? 1 : 0);
    }
}
