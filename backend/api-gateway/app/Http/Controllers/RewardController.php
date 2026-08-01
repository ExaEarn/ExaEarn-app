<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CalculateRewardJob;
use App\Models\UserReward;
use App\Services\ExaPointService;
use App\Services\RewardEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RewardController extends Controller
{
    public function __construct(
        private readonly RewardEngineService $rewardEngine,
        private readonly ExaPointService $exaPoints,
    ) {}

    public function activities(): JsonResponse
    {
        return response()->json([
            'data' => $this->rewardEngine->listActivities(),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        return response()->json([
            'data' => $this->rewardEngine->listRewards($request->user(), $perPage),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        try {
            $reward = $this->rewardEngine->issueReward(
                (int) $request->user()->id,
                'daily_check_in',
                '1',
                [
                    'activity_key' => now()->toDateString(),
                    'ip_address' => $request->ip(),
                    'fingerprint_hash' => $this->fingerprintHash($request->input('device_fingerprint')),
                ]
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $reward], 201);
    }

    public function points(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->dailyProgress((int) $request->user()->id),
        ]);
    }

    public function checkInForHome(Request $request): JsonResponse
    {
        try {
            $reward = $this->rewardEngine->issueReward(
                (int) $request->user()->id,
                'daily_check_in',
                '1',
                [
                    'activity_key' => now()->toDateString(),
                    'ip_address' => $request->ip(),
                    'fingerprint_hash' => $this->fingerprintHash(
                        $request->input('device_fingerprint')
                            ?: $request->header('X-Device-Fingerprint')
                    ),
                ]
            );
        } catch (RuntimeException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'already_claimed',
                    'message' => 'Daily reward already claimed today.',
                    'data' => [
                        'reward_points' => 0,
                        'progress' => $this->dailyProgress((int) $request->user()->id),
                    ],
                ], 409);
            }

            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $progress = $this->dailyProgress((int) $request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Daily reward claimed.',
            'data' => [
                'reward_points' => (int) $reward->reward_amount,
                'current_streak' => $progress['current_streak'],
                'highest_streak' => $progress['highest_streak'],
                'available_points' => $progress['available_points'],
                'total_points' => $progress['total_points'],
                'mystery_box_available' => false,
                'progress' => $progress,
            ],
        ]);
    }

    public function checkInHistory(Request $request): JsonResponse
    {
        $checkins = UserReward::query()
            ->where('user_id', $request->user()->id)
            ->where('activity_type', 'daily_check_in')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->values()
            ->map(fn (UserReward $reward, int $index): array => [
                'reward_points' => (int) $reward->reward_amount,
                'streak_day' => $index + 1,
                'checkin_date' => $reward->activity_key,
                'created_at' => $reward->created_at?->toISOString(),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'checkins' => $checkins,
                'mystery_boxes' => [],
                'daily_probabilities' => [
                    ['points' => (int) config('rewards.activities.daily_check_in.reward_rate', 1), 'weight' => 1],
                ],
                'mystery_probabilities' => [],
            ],
        ]);
    }

    public function record(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'activity_type' => ['required', 'string', 'max:64'],
            'activity_value' => ['required', 'numeric', 'gt:0'],
            'activity_key' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        CalculateRewardJob::dispatch(
            (int) ($payload['user_id'] ?? $request->user()->id),
            (string) $payload['activity_type'],
            (string) $payload['activity_value'],
            array_merge($payload['metadata'] ?? [], [
                'activity_key' => $payload['activity_key'] ?? null,
                'ip_address' => $request->ip(),
                'fingerprint_hash' => $this->fingerprintHash($request->input('device_fingerprint')),
            ])
        )->onQueue('rewards');

        return response()->json([
            'status' => 'accepted',
        ], 202);
    }

    public function claim(Request $request, int $rewardId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:255'],
        ]);

        try {
            $reward = $this->rewardEngine->queueDistribution(
                (int) $request->user()->id,
                $rewardId,
                (string) $payload['wallet_address']
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => $reward,
        ], 202);
    }

    private function fingerprintHash(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash('sha256', trim($value));
    }

    private function dailyProgress(int $userId): array
    {
        $balance = $this->exaPoints->getBalance($userId);
        $available = (int) (float) ($balance['available_points'] ?? 0);
        $locked = (int) (float) ($balance['locked_points'] ?? 0);
        $target = 5000;
        $checkins = UserReward::query()
            ->where('user_id', $userId)
            ->where('activity_type', 'daily_check_in')
            ->whereNotIn('status', ['blocked', 'rejected'])
            ->count();

        return [
            'total_points' => $available + $locked,
            'available_points' => $available,
            'redeemed_points' => 0,
            'lifetime_points' => (int) (float) ($balance['total_points'] ?? $available + $locked),
            'progress_percentage' => round(min(100, ($available / $target) * 100), 2),
            'current_streak' => $checkins,
            'highest_streak' => $checkins,
            'mystery_box_available' => false,
            'estimated_days_to_redeem' => max(0, $target - $available),
            'redemption_target_points' => $target,
            'redemption_value_usdt' => 5,
        ];
    }
}
