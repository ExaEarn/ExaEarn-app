<?php

namespace App\Services;

use App\Models\CheckinStreak;
use App\Models\DailyCheckin;
use App\Models\MysteryBox;
use App\Models\RewardRedemption;
use App\Models\TradingCredit;
use App\Models\User;
use App\Models\UserPoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RewardService
{
    public function generateDailyReward(): int
    {
        return $this->weightedReward(config('checkin.daily_rewards'));
    }

    public function generateMysteryReward(): int
    {
        return $this->weightedReward(config('checkin.mystery_rewards'));
    }

    public function claimDaily(User $user, string $ipAddress, string $deviceHash, array $metadata = []): array
    {
        $today = CarbonImmutable::today();
        $cacheKey = "checkin:claimed:{$user->id}:{$today->toDateString()}";
        $lock = Cache::lock("checkin:lock:{$user->id}", 10);

        return $lock->block(3, function () use ($user, $ipAddress, $deviceHash, $metadata, $today, $cacheKey) {
            if (Cache::get($cacheKey)) {
                throw new RuntimeException('Daily reward already claimed.');
            }

            return DB::transaction(function () use ($user, $ipAddress, $deviceHash, $metadata, $today, $cacheKey) {
                $existing = DailyCheckin::query()
                    ->where('user_id', $user->id)
                    ->whereDate('checkin_date', $today->toDateString())
                    ->first();

                if ($existing) {
                    Cache::put($cacheKey, true, $today->endOfDay());
                    throw new RuntimeException('Daily reward already claimed.');
                }

                $streak = CheckinStreak::query()->lockForUpdate()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['current_streak' => 0, 'highest_streak' => 0]
                );

                $nextStreak = $this->nextStreakDay($streak, $today);
                $reward = $this->generateDailyReward();

                $checkin = DailyCheckin::create([
                    'user_id' => $user->id,
                    'reward_points' => $reward,
                    'streak_day' => $nextStreak,
                    'checkin_date' => $today->toDateString(),
                    'ip_address' => $ipAddress,
                    'device_hash' => $deviceHash,
                    'metadata' => $metadata,
                ]);

                $streak->forceFill([
                    'current_streak' => $nextStreak,
                    'highest_streak' => max($streak->highest_streak, $nextStreak),
                    'last_checkin_date' => $today->toDateString(),
                ])->save();

                $points = $this->creditPoints($user, $reward);

                Cache::put($cacheKey, true, $today->endOfDay());
                Cache::put("checkin:streak:{$user->id}", [
                    'current_streak' => $streak->current_streak,
                    'highest_streak' => $streak->highest_streak,
                    'last_checkin_date' => $streak->last_checkin_date?->toDateString(),
                ], now()->addDays(8));

                return [
                    'checkin' => $checkin,
                    'reward_points' => $reward,
                    'points' => $points->fresh(),
                    'streak' => $streak->fresh(),
                    'mystery_box_available' => $nextStreak >= (int) config('checkin.mystery_streak_days'),
                ];
            });
        });
    }

    public function openMysteryBox(User $user): array
    {
        $lock = Cache::lock("checkin:mystery:lock:{$user->id}", 10);

        return $lock->block(3, function () use ($user) {
            return DB::transaction(function () use ($user) {
                $streak = CheckinStreak::query()->lockForUpdate()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['current_streak' => 0, 'highest_streak' => 0]
                );

                $requiredStreak = (int) config('checkin.mystery_streak_days');
                if ($streak->current_streak < $requiredStreak) {
                    throw new RuntimeException('Mystery box unlocks after 7 consecutive check-ins.');
                }

                $cycle = intdiv((int) $streak->highest_streak, $requiredStreak);
                $alreadyOpened = MysteryBox::query()
                    ->where('user_id', $user->id)
                    ->where('streak_cycle', $cycle)
                    ->exists();

                if ($alreadyOpened) {
                    throw new RuntimeException('Mystery box already opened for this streak cycle.');
                }

                $reward = $this->generateMysteryReward();
                $box = MysteryBox::create([
                    'user_id' => $user->id,
                    'reward_points' => $reward,
                    'streak_cycle' => max(1, $cycle),
                    'opened_at' => now(),
                ]);

                $points = $this->creditPoints($user, $reward);

                $streak->forceFill([
                    'current_streak' => 0,
                    'last_checkin_date' => null,
                ])->save();

                Cache::forget("checkin:streak:{$user->id}");

                return [
                    'mystery_box' => $box,
                    'reward_points' => $reward,
                    'points' => $points->fresh(),
                    'streak' => $streak->fresh(),
                    'streak_reset' => true,
                ];
            });
        });
    }

    public function redeemTradingCredit(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $points = UserPoint::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
            $threshold = (int) config('checkin.redemption_threshold_points');

            if ($points->available_points < $threshold) {
                throw new RuntimeException('Minimum 5000 points required to redeem.');
            }

            if (! $user->email_verified_at) {
                throw new RuntimeException('Verified account required before redemption.');
            }

            if ($user->created_at && $user->created_at->gt(now()->subDays(7))) {
                throw new RuntimeException('Account must be older than 7 days before redemption.');
            }

            $usdtValue = (string) config('checkin.redemption_usdt_value');

            $points->forceFill([
                'available_points' => $points->available_points - $threshold,
                'redeemed_points' => $points->redeemed_points + $threshold,
            ])->save();

            $redemption = RewardRedemption::create([
                'user_id' => $user->id,
                'points_used' => $threshold,
                'usdt_value' => $usdtValue,
                'redemption_type' => 'trading_credit',
                'status' => 'approved',
                'metadata' => [
                    'reference' => 'rdm_'.Str::uuid()->toString(),
                    'rules' => ['futures_only', 'non_withdrawable_credit', 'profits_withdrawable'],
                ],
            ]);

            $credit = TradingCredit::create([
                'user_id' => $user->id,
                'amount' => $usdtValue,
                'source' => 'daily_checkin_redemption',
                'locked' => true,
                'withdrawable_profit' => 0,
                'expires_at' => now()->addDays((int) config('checkin.trading_credit_expiry_days')),
            ]);

            return [
                'points' => $points->fresh(),
                'redemption' => $redemption,
                'trading_credit' => $credit,
            ];
        });
    }

    public function progress(User $user): array
    {
        $points = UserPoint::query()->firstOrCreate(['user_id' => $user->id]);
        $streak = CheckinStreak::query()->firstOrCreate(['user_id' => $user->id]);
        $threshold = (int) config('checkin.redemption_threshold_points');
        $available = (int) $points->available_points;
        $remaining = max(0, $threshold - $available);
        $averageDaily = (float) config('checkin.daily_average_points');

        return [
            'total_points' => (int) $points->total_points,
            'available_points' => $available,
            'redeemed_points' => (int) $points->redeemed_points,
            'lifetime_points' => (int) $points->lifetime_points,
            'progress_percentage' => round(min(100, ($available / $threshold) * 100), 2),
            'current_streak' => (int) $streak->current_streak,
            'highest_streak' => (int) $streak->highest_streak,
            'mystery_box_available' => $streak->current_streak >= (int) config('checkin.mystery_streak_days'),
            'estimated_days_to_redeem' => $remaining === 0 ? 0 : (int) ceil($remaining / max(0.1, $averageDaily)),
            'redemption_target_points' => $threshold,
            'redemption_value_usdt' => (float) config('checkin.redemption_usdt_value'),
        ];
    }

    private function creditPoints(User $user, int $amount): UserPoint
    {
        $points = UserPoint::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);

        if ($amount <= 0) {
            return $points;
        }

        $points->forceFill([
            'total_points' => $points->total_points + $amount,
            'available_points' => $points->available_points + $amount,
            'lifetime_points' => $points->lifetime_points + $amount,
        ])->save();

        return $points;
    }

    private function nextStreakDay(CheckinStreak $streak, CarbonImmutable $today): int
    {
        if (! $streak->last_checkin_date) {
            return 1;
        }

        $last = CarbonImmutable::parse($streak->last_checkin_date)->startOfDay();

        if ($last->equalTo($today->subDay())) {
            return $streak->current_streak + 1;
        }

        if ($last->equalTo($today)) {
            return $streak->current_streak;
        }

        return 1;
    }

    private function weightedReward(array $distribution): int
    {
        $total = array_sum(array_column($distribution, 'weight'));
        $roll = random_int(1, $total);
        $cursor = 0;

        foreach ($distribution as $bucket) {
            $cursor += (int) $bucket['weight'];
            if ($roll <= $cursor) {
                return (int) $bucket['points'];
            }
        }

        return 0;
    }
}
