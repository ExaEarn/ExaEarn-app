<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LoginDevice;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Log;

class RiskPolicyService
{
    /**
     * Assess withdrawal risk and return block/allow decision.
     */
    public function assessWithdrawal(User $user, string $currency, string $amount): array
    {
        $riskScore = 0;
        $flags = [];

        // 1. Device mismatch
        if ($this->isNewDevice($user)) {
            $riskScore += 25;
            $flags[] = 'new_device';
        }

        // 2. Geo-location anomaly
        if ($this->isGeoAnomaly($user)) {
            $riskScore += 30;
            $flags[] = 'geo_anomaly';
        }

        // 3. Withdrawal velocity (frequency)
        if ($this->isWithdrawalFrequent($user)) {
            $riskScore += 20;
            $flags[] = 'high_velocity';
        }

        // 4. New address withdrawal
        if ($this->isNewAddress($user, $currency)) {
            $riskScore += 15;
            $flags[] = 'new_address';
        }

        // 5. Large amount
        if ($this->isLargeAmount($currency, $amount)) {
            $riskScore += 20;
            $flags[] = 'large_amount';
        }

        $level = $this->getRiskLevel($riskScore);
        $blocked = $riskScore > 60; // Block if score > 60

        Log::info('Withdrawal risk assessment', [
            'user_id' => $user->id,
            'risk_score' => $riskScore,
            'level' => $level,
            'flags' => $flags,
            'blocked' => $blocked,
        ]);

        return [
            'blocked' => $blocked,
            'level' => $level,
            'score' => $riskScore,
            'flags' => $flags,
            'reason' => $blocked ? 'Withdrawal flagged for manual review due to risk indicators.' : null,
        ];
    }

    // ────────────────────── Detection Methods ──────────────────────

    private function isNewDevice(User $user): bool
    {
        $knownDevices = LoginDevice::where('user_id', $user->id)
            ->distinct('fingerprint_hash')
            ->count('fingerprint_hash');

        return $knownDevices > 1;
    }

    private function isGeoAnomaly(User $user): bool
    {
        // TODO: Use IP / geo tracking for stronger anomaly detection.
        return false;
    }

    private function isWithdrawalFrequent(User $user): bool
    {
        $count = \App\Models\Withdrawal::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(1))
            ->count();

        return $count >= 3;
    }

    private function isNewAddress(User $user, string $currency): bool
    {
        return !Withdrawal::where('user_id', $user->id)
            ->where('currency', strtoupper($currency))
            ->exists();
    }

    private function isLargeAmount(string $currency, string $amount): bool
    {
        $threshold = match (strtoupper($currency)) {
            'NGN' => '5000',
            'USD' => '100',
            'USDT' => '100',
            'BTC' => '0.1',
            'ETH' => '1',
            default => '1000',
        };

        return bccomp($amount, $threshold, 18) > 0;
    }

    private function getRiskLevel(int $score): string
    {
        if ($score < 20) {
            return 'low';
        }
        if ($score < 50) {
            return 'medium';
        }

        return 'high';
    }
}
