<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Jobs\ProcessReferralRewardJob;
use App\Jobs\UpdateReferralLeaderboardJob;
use App\Models\Referral;
use App\Models\ReferralLeaderboard;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReferralService
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly ReferralAbuseService $abuseService,
    ) {
    }

    public function ensureReferralCode(User $user): User
    {
        if ($user->referral_code) {
            return $user;
        }

        do {
            $code = strtoupper(Str::random((int) config('referral.code_length', 8)));
        } while (User::query()->where('referral_code', $code)->exists());

        $user->referral_code = $code;
        $user->save();

        return $user;
    }

    public function bindReferral(User $referredUser, string $code, array $context = []): ?Referral
    {
        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            return null;
        }

        if (Referral::query()->where('referred_user_id', $referredUser->id)->exists()) {
            throw new RuntimeException('Referral relationship is already bound.');
        }

        $referrer = User::query()
            ->where('referral_code', $normalizedCode)
            ->whereKeyNot($referredUser->id)
            ->first();

        if (!$referrer) {
            throw new RuntimeException('Referral code is invalid.');
        }

        if ($this->createsLoop($referrer->id, $referredUser->id)) {
            throw new RuntimeException('Referral loop detected.');
        }

        $referral = Referral::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referredUser->id,
            'referral_code' => $normalizedCode,
            'metadata' => [
                'ip_address' => $context['ip_address'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'fingerprint_hash' => $context['fingerprint_hash'] ?? null,
            ],
            'created_at' => now(),
        ]);

        UpdateReferralLeaderboardJob::dispatch($referrer->id)->onQueue('referrals');

        return $referral;
    }

    public function queueQualifiedActivity(int $referredUserId, string $activityType, array $metadata = []): void
    {
        if (!config('referral.enabled', true)) {
            return;
        }

        ProcessReferralRewardJob::dispatch($referredUserId, $activityType, $metadata)->onQueue('referrals');
    }

    public function processQualifiedActivity(int $referredUserId, string $activityType, array $metadata = []): void
    {
        $activityConfig = config("referral.activities.{$activityType}");
        if (!$activityConfig) {
            return;
        }

        $referredUser = User::query()->findOrFail($referredUserId);
        $referral = Referral::query()->where('referred_user_id', $referredUserId)->first();
        if (!$referral) {
            return;
        }

        if ((bool) config('referral.require_email_verification', true) && !$referredUser->email_verified_at) {
            return;
        }

        if ((bool) config('referral.require_kyc_for_rewards', false) && !$referredUser->kyc_verified_at) {
            return;
        }

        if (($activityConfig['first_only'] ?? true)
            && ReferralReward::query()
                ->where('referred_user_id', $referredUserId)
                ->where('activity_type', $activityType)
                ->exists()) {
            return;
        }

        $eventKey = $this->resolveEventKey($activityType, $metadata);
        if (ReferralReward::query()
            ->where('referred_user_id', $referredUserId)
            ->where('activity_type', $activityType)
            ->where('event_key', $eventKey)
            ->exists()) {
            return;
        }

        $flags = $this->abuseService->inspectUser($referredUser, $metadata);
        if ($flags !== []) {
            $this->abuseService->suspendUser($referredUser, $flags);
            $this->createSuspendedRewards($referral, $activityType, $eventKey, $metadata, $flags);
            return;
        }

        $this->distributeReferralRewards($referral, $activityType, $eventKey, $metadata);
    }

    public function getDashboardSummary(User $user): array
    {
        $user = $this->ensureReferralCode($user);

        return [
            'referral_code' => $user->referral_code,
            'referral_link' => rtrim((string) config('referral.frontend_register_url'), '/') . '?ref=' . $user->referral_code,
            'stats' => [
                'total_invites' => Referral::query()->where('referrer_user_id', $user->id)->count(),
                'active_invites' => ReferralReward::query()
                    ->where('referrer_id', $user->id)
                    ->where('level', 1)
                    ->whereIn('status', ['approved', 'paid'])
                    ->distinct('referred_user_id')
                    ->count('referred_user_id'),
                'total_rewards' => (string) ReferralReward::query()
                    ->where('referrer_id', $user->id)
                    ->whereIn('status', ['approved', 'paid'])
                    ->sum('reward_amount'),
            ],
            'security' => [
                'rewards_suspended_until' => $user->reward_suspended_until,
                'risk_flags' => $user->reward_risk_flags['flags'] ?? [],
            ],
            'recent_rewards' => ReferralReward::query()
                ->where('referrer_id', $user->id)
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }

    public function rewardsForUser(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return ReferralReward::query()
            ->where('referrer_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function leaderboard(string $timeframe, int $limit): Collection
    {
        [$periodStart] = $this->resolvePeriod($timeframe);

        return ReferralLeaderboard::query()
            ->with('user:id,name,email,referral_code')
            ->where('timeframe', $timeframe)
            ->where('period_start', $periodStart)
            ->orderByDesc('total_rewards')
            ->orderByDesc('active_invites')
            ->orderByDesc('total_invites')
            ->limit($limit)
            ->get();
    }

    public function recalculateLeaderboard(int $userId, string $timeframe): void
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($timeframe);

        $totalInvites = Referral::query()
            ->where('referrer_user_id', $userId)
            ->where('created_at', '>=', $periodStart)
            ->when($periodEnd, fn ($query) => $query->where('created_at', '<', $periodEnd))
            ->count();

        $activeInvites = ReferralReward::query()
            ->where('referrer_id', $userId)
            ->where('level', 1)
            ->whereIn('status', ['approved', 'paid'])
            ->where('created_at', '>=', $periodStart)
            ->when($periodEnd, fn ($query) => $query->where('created_at', '<', $periodEnd))
            ->distinct('referred_user_id')
            ->count('referred_user_id');

        $totalRewards = (string) ReferralReward::query()
            ->where('referrer_id', $userId)
            ->whereIn('status', ['approved', 'paid'])
            ->where('created_at', '>=', $periodStart)
            ->when($periodEnd, fn ($query) => $query->where('created_at', '<', $periodEnd))
            ->sum('reward_amount');

        ReferralLeaderboard::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'timeframe' => $timeframe,
                'period_start' => $periodStart,
            ],
            [
                'period_end' => $periodEnd,
                'total_invites' => $totalInvites,
                'active_invites' => $activeInvites,
                'total_rewards' => $totalRewards,
                'updated_at' => now(),
            ]
        );
    }

    private function distributeReferralRewards(Referral $rootReferral, string $activityType, string $eventKey, array $metadata): void
    {
        $rewardBase = (string) config("referral.activities.{$activityType}.reward", '0');
        $rewardToken = (string) config('referral.reward_token', 'EXA');

        DB::transaction(function () use ($rootReferral, $activityType, $eventKey, $metadata, $rewardBase, $rewardToken): void {
            $ancestorReferral = $rootReferral;

            foreach ((array) config('referral.levels', []) as $level => $share) {
                if (!$ancestorReferral) {
                    break;
                }

                $referrer = User::query()->lockForUpdate()->find($ancestorReferral->referrer_user_id);
                if (!$referrer) {
                    break;
                }

                $amount = $this->mul($rewardBase, (string) $share);
                $transaction = $this->transactions->recordReward(
                    $referrer->id,
                    TransactionType::ReferralReward,
                    $rewardToken,
                    $amount,
                    "referral:{$activityType}:{$eventKey}",
                    [
                        'referred_user_id' => $ancestorReferral->referred_user_id,
                        'activity_type' => $activityType,
                        'level' => (int) $level,
                    ]
                );

                ReferralReward::query()->create([
                    'referrer_id' => $referrer->id,
                    'referred_user_id' => $ancestorReferral->referred_user_id,
                    'reward_amount' => $amount,
                    'reward_token' => $rewardToken,
                    'activity_type' => $activityType,
                    'level' => (int) $level,
                    'status' => 'paid',
                    'event_key' => $eventKey,
                    'transaction_id' => $transaction->id,
                    'metadata' => $metadata,
                    'approved_at' => now(),
                ]);

                UpdateReferralLeaderboardJob::dispatch($referrer->id)->onQueue('referrals');
                $ancestorReferral = Referral::query()->where('referred_user_id', $ancestorReferral->referrer_user_id)->first();
            }
        });
    }

    private function createSuspendedRewards(Referral $rootReferral, string $activityType, string $eventKey, array $metadata, array $flags): void
    {
        $ancestorReferral = $rootReferral;

        foreach (array_keys((array) config('referral.levels', [])) as $level) {
            if (!$ancestorReferral) {
                break;
            }

            ReferralReward::query()->create([
                'referrer_id' => $ancestorReferral->referrer_user_id,
                'referred_user_id' => $ancestorReferral->referred_user_id,
                'reward_amount' => $this->mul(
                    (string) config("referral.activities.{$activityType}.reward", '0'),
                    (string) config("referral.levels.{$level}", '0')
                ),
                'reward_token' => (string) config('referral.reward_token', 'EXA'),
                'activity_type' => $activityType,
                'level' => (int) $level,
                'status' => 'suspended',
                'event_key' => $eventKey,
                'metadata' => array_merge($metadata, ['flags' => $flags]),
            ]);

            UpdateReferralLeaderboardJob::dispatch($ancestorReferral->referrer_user_id)->onQueue('referrals');
            $ancestorReferral = Referral::query()->where('referred_user_id', $ancestorReferral->referrer_user_id)->first();
        }
    }

    private function createsLoop(int $referrerUserId, int $referredUserId): bool
    {
        $cursor = Referral::query()->where('referred_user_id', $referrerUserId)->first();

        while ($cursor) {
            if ((int) $cursor->referrer_user_id === $referredUserId) {
                return true;
            }

            $cursor = Referral::query()->where('referred_user_id', $cursor->referrer_user_id)->first();
        }

        return false;
    }

    private function resolveEventKey(string $activityType, array $metadata): string
    {
        return (string) ($metadata['event_key']
            ?? $metadata['transaction_id']
            ?? $metadata['trade_uuid']
            ?? $metadata['stake_id']
            ?? "first:{$activityType}");
    }

    private function resolvePeriod(string $timeframe): array
    {
        return match ($timeframe) {
            'weekly' => [now()->startOfWeek(), now()->startOfWeek()->copy()->addWeek()],
            'monthly' => [now()->startOfMonth(), now()->startOfMonth()->copy()->addMonth()],
            'all_time' => [Carbon::createFromTimestamp(0), null],
            default => throw new RuntimeException('Unsupported leaderboard timeframe.'),
        };
    }

    private function mul(string $left, string $right): string
    {
        if (function_exists('bcmul')) {
            return bcmul($left, $right, 8);
        }

        return number_format((float) $left * (float) $right, 8, '.', '');
    }
}
