<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginDevice;
use App\Models\User;
use App\Models\UserReward;

class RewardSecurityService
{
    public function inspect(User $user, string $activityType, array $context = []): array
    {
        $flags = [];

        if ($user->reward_suspended_until && $user->reward_suspended_until->isFuture()) {
            $flags[] = 'reward_suspension_active';
        }

        $recentRewards = UserReward::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentRewards >= (int) config('rewards.max_rewards_per_hour', 20)) {
            $flags[] = 'high_reward_frequency';
        }

        $ipAddress = (string) ($context['ip_address'] ?? '');
        if ($ipAddress !== '') {
            $sharedIpUsers = LoginDevice::query()
                ->where('ip_address', $ipAddress)
                ->distinct('user_id')
                ->count('user_id');

            if ($sharedIpUsers >= (int) config('referral.abuse.shared_ip_limit', 3)) {
                $flags[] = 'shared_ip_limit';
            }
        }

        $fingerprintHash = isset($context['fingerprint_hash']) ? (string) $context['fingerprint_hash'] : null;
        if ($fingerprintHash) {
            $sharedFingerprintUsers = LoginDevice::query()
                ->where('fingerprint_hash', $fingerprintHash)
                ->distinct('user_id')
                ->count('user_id');

            if ($sharedFingerprintUsers >= (int) config('referral.abuse.shared_fingerprint_limit', 2)) {
                $flags[] = 'shared_device_fingerprint';
            }
        }

        if (
            (bool) config('rewards.require_kyc_for_high_risk', false)
            && in_array($activityType, (array) config('rewards.high_risk_activities', []), true)
            && !$user->kyc_verified_at
        ) {
            $flags[] = 'kyc_required';
        }

        return array_values(array_unique($flags));
    }

    public function suspend(User $user, array $flags): void
    {
        if ($flags === []) {
            return;
        }

        $riskFlags = $user->reward_risk_flags ?? [];
        $riskFlags['flags'] = array_values(array_unique(array_merge($riskFlags['flags'] ?? [], $flags)));
        $riskFlags['last_flagged_at'] = now()->toISOString();

        $user->reward_risk_flags = $riskFlags;
        $user->reward_suspended_until = now()->addHours(24);
        $user->save();
    }
}
