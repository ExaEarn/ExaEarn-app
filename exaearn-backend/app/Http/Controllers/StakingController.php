<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StakingController extends Controller
{
    public function __construct(private readonly StakingService $stakingService)
    {
    }

    public function pools(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->listPools()]);
    }

    public function myStakes(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->userStakes((int) $request->user()->id)]);
    }

    public function createPool(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'asset' => ['required', 'string', 'max:16'],
            'reward_token' => ['nullable', 'string', 'max:16'],
            'contract_pool_id' => ['nullable', 'integer', 'min:1'],
            'lock_period' => ['required', 'integer', 'min:1'],
            'reward_rate' => ['required', 'numeric', 'gt:0'],
            'reward_multiplier' => ['nullable', 'numeric', 'gt:0'],
            'pool_size' => ['required', 'numeric', 'gt:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'metadata' => ['nullable', 'array'],
        ]);

        $pool = $this->stakingService->createPool($payload);
        return response()->json(['data' => $pool], 201);
    }

    public function stake(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'pool_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'auto_compound' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->stakingService->stake(
                (int) $request->user()->id,
                (int) $payload['pool_id'],
                (string) $payload['amount'],
                (bool) ($payload['auto_compound'] ?? false)
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }

    public function claim(Request $request, int $stakeId): JsonResponse
    {
        try {
            $result = $this->stakingService->claimStakeRewards((int) $request->user()->id, $stakeId);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function compound(Request $request, int $stakeId): JsonResponse
    {
        try {
            $result = $this->stakingService->compoundStakeRewards((int) $request->user()->id, $stakeId);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function unstake(Request $request, int $stakeId): JsonResponse
    {
        $payload = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            $result = $this->stakingService->unstake(
                (int) $request->user()->id,
                $stakeId,
                isset($payload['amount']) ? (string) $payload['amount'] : null
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }
}
