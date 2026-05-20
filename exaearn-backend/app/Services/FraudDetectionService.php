<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FraudLog;
use App\Models\LoginDevice;
use App\Models\SuspiciousUser;
use App\Models\User;

class FraudDetectionService
{
    public function recordFailedLogin(?User $user, string $ip, string $userAgent): void
    {
        if (!$user) {
            return;
        }

        $this->createFraudLog($user, 35, 'MEDIUM', [
            'event' => 'user.failed_login',
            'reason' => 'Failed login attempt recorded',
            'ip' => $ip,
            'device' => $userAgent,
        ]);

        $this->flagSuspiciousUser($user, 'MEDIUM', [
            'last_failed_login_ip' => $ip,
            'last_failed_login_at' => now()->toISOString(),
        ]);
    }

    public function analyzeLogin(User $user, string $ip, string $userAgent, ?string $fingerprint = null): array
    {
        $flags = [];
        $riskScore = 0;

        $knownDevice = LoginDevice::query()
            ->where('user_id', $user->id)
            ->where('ip_address', $ip)
            ->exists();

        if (!$knownDevice) {
            $riskScore += 30;
            $flags[] = 'new_device_or_ip';
        }

        if ($fingerprint !== null && trim($fingerprint) !== '') {
            $hash = hash('sha256', trim($fingerprint));
            $knownFingerprint = LoginDevice::query()
                ->where('user_id', $user->id)
                ->where('fingerprint_hash', $hash)
                ->exists();

            if (!$knownFingerprint) {
                $riskScore += 25;
                $flags[] = 'new_fingerprint';
            }
        }

        $result = [
            'risk_score' => $riskScore,
            'risk_level' => $this->riskLevel($riskScore),
            'flags' => $flags,
        ];

        if ($riskScore >= 50) {
            $this->createFraudLog($user, $riskScore, $result['risk_level'], [
                'event' => 'suspicious.activity',
                'reason' => 'Suspicious login pattern detected',
                'ip' => $ip,
                'device' => $userAgent,
                'flags' => $flags,
            ]);

            event('suspicious.activity', [
                'user_id' => $user->id,
                'source' => 'login',
                'risk_score' => $riskScore,
                'risk_level' => $result['risk_level'],
                'flags' => $flags,
            ]);

            $this->flagSuspiciousUser($user, $result['risk_level'], [
                'type' => 'login_anomaly',
                'ip' => $ip,
                'flags' => $flags,
            ]);
        }

        return $result;
    }

    public function analyzeWithdrawal(User $user, string $amount): array
    {
        $riskScore = 0;
        $flags = [];

        $threshold = (string) config('security.transactions.large_withdrawal_threshold', '2000');
        if (bccomp($amount, $threshold, 8) === 1) {
            $riskScore += 45;
            $flags[] = 'large_withdrawal';
        }

        $rapidCount = (int) $user->withdrawals()
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($rapidCount >= 3) {
            $riskScore += 40;
            $flags[] = 'rapid_withdrawals';
        }

        $result = [
            'risk_score' => $riskScore,
            'risk_level' => $this->riskLevel($riskScore),
            'flags' => $flags,
        ];

        if ($riskScore >= 50) {
            $this->createFraudLog($user, $riskScore, $result['risk_level'], [
                'event' => 'withdrawal.requested',
                'reason' => 'Abnormal withdrawal pattern detected',
                'flags' => $flags,
                'amount' => $amount,
            ]);

            event('suspicious.activity', [
                'user_id' => $user->id,
                'source' => 'withdrawal',
                'risk_score' => $riskScore,
                'risk_level' => $result['risk_level'],
                'flags' => $flags,
                'amount' => $amount,
            ]);

            $this->flagSuspiciousUser($user, $result['risk_level'], [
                'type' => 'withdrawal_anomaly',
                'amount' => $amount,
                'flags' => $flags,
            ]);
        }

        return $result;
    }

    private function createFraudLog(User $user, int $score, string $level, array $payload): void
    {
        FraudLog::query()->create([
            'user_id' => $user->id,
            'order_id' => null,
            'risk_score' => $score,
            'risk_level' => $level,
            'reason' => [$payload['reason'] ?? 'Security risk event'],
            'ip' => $payload['ip'] ?? request()?->ip(),
            'device' => $payload['device'] ?? (string) request()?->userAgent(),
            'payload' => $payload,
        ]);
    }

    private function flagSuspiciousUser(User $user, string $riskLevel, array $metadata): void
    {
        $existing = SuspiciousUser::query()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->update([
                'risk_level' => $riskLevel,
                'flag_count' => ((int) $existing->flag_count) + 1,
                'status' => $riskLevel === 'HIGH' ? 'frozen' : 'review',
                'metadata' => array_merge($existing->metadata ?? [], $metadata),
            ]);

            return;
        }

        SuspiciousUser::query()->create([
            'user_id' => $user->id,
            'risk_level' => $riskLevel,
            'flag_count' => 1,
            'status' => $riskLevel === 'HIGH' ? 'frozen' : 'review',
            'metadata' => $metadata,
        ]);
    }

    private function riskLevel(int $riskScore): string
    {
        return match (true) {
            $riskScore >= 80 => 'HIGH',
            $riskScore >= 50 => 'MEDIUM',
            default => 'LOW',
        };
    }
}

