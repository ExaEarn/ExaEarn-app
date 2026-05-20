<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DepositAddress;
use App\Models\LoginDevice;
use App\Models\User;

class ReferralAbuseService
{
    public function inspectUser(User $user, array $metadata = []): array
    {
        $flags = [];

        if ($user->reward_suspended_until && $user->reward_suspended_until->isFuture()) {
            $flags[] = 'existing_suspension';
        }

        $ipAddress = (string) ($metadata['ip_address'] ?? '');
        if ($ipAddress !== '') {
            $sharedIpUsers = LoginDevice::query()
                ->where('ip_address', $ipAddress)
                ->distinct('user_id')
                ->count('user_id');

            if ($sharedIpUsers >= (int) config('referral.abuse.shared_ip_limit', 3)) {
                $flags[] = 'shared_ip_limit';
            }
        }

        $fingerprintHash = isset($metadata['fingerprint_hash']) ? (string) $metadata['fingerprint_hash'] : null;
        if ($fingerprintHash) {
            $sharedFingerprints = LoginDevice::query()
                ->where('fingerprint_hash', $fingerprintHash)
                ->distinct('user_id')
                ->count('user_id');

            if ($sharedFingerprints >= (int) config('referral.abuse.shared_fingerprint_limit', 2)) {
                $flags[] = 'shared_device_fingerprint';
            }
        }

        $walletAddress = strtolower((string) ($metadata['wallet_address'] ?? ''));
        if ($walletAddress !== '') {
            $sharedWallets = DepositAddress::query()
                ->whereRaw('LOWER(address) = ?', [$walletAddress])
                ->distinct('user_id')
                ->count('user_id');

            if ($sharedWallets >= (int) config('referral.abuse.shared_wallet_limit', 1)) {
                $flags[] = 'reused_wallet_address';
            }
        }

        return array_values(array_unique($flags));
    }

    public function suspendUser(User $user, array $flags): void
    {
        if ($flags === []) {
            return;
        }

        $riskFlags = $user->reward_risk_flags ?? [];
        $riskFlags['flags'] = array_values(array_unique(array_merge($riskFlags['flags'] ?? [], $flags)));
        $riskFlags['last_flagged_at'] = now()->toISOString();

        $user->reward_risk_flags = $riskFlags;
        $user->reward_suspended_until = now()->addHours((int) config('referral.suspend_hours', 72));
        $user->save();
    }
}
