<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referralService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->referralService->getDashboardSummary($request->user()),
        ]);
    }

    public function rewards(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        return response()->json([
            'data' => $this->referralService->rewardsForUser($request->user(), $perPage),
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $timeframe = (string) $request->query('timeframe', 'weekly');
        $limit = max(1, min(100, (int) $request->query('limit', config('referral.leaderboard.default_limit', 25))));

        try {
            $data = $this->referralService->leaderboard($timeframe, $limit);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $data]);
    }
}
