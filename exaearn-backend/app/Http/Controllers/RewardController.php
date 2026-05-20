<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CalculateRewardJob;
use App\Services\RewardEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RewardController extends Controller
{
    public function __construct(private readonly RewardEngineService $rewardEngine)
    {
    }

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
        if (!$value) {
            return null;
        }

        return hash('sha256', trim($value));
    }
}
