<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class TransactionGuardService
{
    public function __construct(
        private readonly RateLimiterService $rateLimiter,
        private readonly FraudDetectionService $fraudDetectionService,
    ) {
    }

    public function guardWithdrawal(User $user, string $amount): array
    {
        if ($user->withdrawal_locked_until && now()->lt($user->withdrawal_locked_until)) {
            throw new \RuntimeException('Withdrawals are temporarily locked for your account.');
        }

        $maxPerMinute = (int) config('security.transactions.max_withdrawal_per_minute', 3);
        $rateKey = sprintf('security:withdrawal:user:%d', $user->id);

        $this->rateLimiter->assertWithinLimit(
            $rateKey,
            $maxPerMinute,
            60,
            'Withdrawal frequency exceeded.'
        );

        $dailyLimit = (string) config('security.transactions.withdrawal_daily_limit', '10000');
        $todayTotal = (string) $user->withdrawals()
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->sum('amount');

        if (bccomp(bcadd($todayTotal, $amount, 8), $dailyLimit, 8) === 1) {
            throw new \RuntimeException('Withdrawal exceeds daily security limit.');
        }

        $maxPerDay = (int) config('security.transactions.max_withdrawal_per_day', 20);
        $countToday = (int) $user->withdrawals()
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->count();

        if ($countToday >= $maxPerDay) {
            throw new \RuntimeException('Daily withdrawal frequency exceeded.');
        }

        $risk = $this->fraudDetectionService->analyzeWithdrawal($user, $amount);
        if (($risk['risk_level'] ?? 'LOW') === 'HIGH') {
            throw new \RuntimeException('Withdrawal blocked for security review.');
        }

        return [
            'delay_seconds' => (int) config('security.transactions.withdrawal_delay_seconds', 60),
            'risk' => $risk,
        ];
    }
}

