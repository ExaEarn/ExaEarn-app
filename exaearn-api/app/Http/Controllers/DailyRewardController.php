<?php

namespace App\Http\Controllers;

use App\Jobs\ScanCheckinFraud;
use App\Models\DailyCheckin;
use App\Models\MysteryBox;
use App\Services\CheckinFraudService;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DailyRewardController extends Controller
{
    public function __construct(
        protected RewardService $rewards,
        protected CheckinFraudService $fraud,
    ) {
    }

    public function points(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->rewards->progress($request->user()),
        ]);
    }

    public function checkin(Request $request): JsonResponse
    {
        try {
            $context = $this->fraud->assertClaimAllowed($request->user(), $request);

            $result = $this->rewards->claimDaily(
                $request->user(),
                $context['ip_address'],
                $context['device_hash'],
                [
                    'risk_score' => $context['risk_score'],
                    'user_agent' => $request->userAgent(),
                ],
            );

            ScanCheckinFraud::dispatch($result['checkin']->id)->afterCommit();

            return response()->json([
                'status' => 'success',
                'message' => 'Daily reward claimed.',
                'data' => [
                    'reward_points' => $result['reward_points'],
                    'current_streak' => $result['streak']->current_streak,
                    'highest_streak' => $result['streak']->highest_streak,
                    'available_points' => $result['points']->available_points,
                    'total_points' => $result['points']->total_points,
                    'mystery_box_available' => $result['mystery_box_available'],
                    'progress' => $this->rewards->progress($request->user()),
                ],
            ]);
        } catch (RuntimeException $exception) {
            return $this->rewardError($exception->getMessage());
        }
    }

    public function openMysteryBox(Request $request): JsonResponse
    {
        try {
            $this->fraud->assertRedemptionAllowed($request->user(), $request);
            $result = $this->rewards->openMysteryBox($request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Mystery box opened.',
                'data' => [
                    'reward_points' => $result['reward_points'],
                    'available_points' => $result['points']->available_points,
                    'total_points' => $result['points']->total_points,
                    'streak_reset' => $result['streak_reset'],
                    'current_streak' => $result['streak']->current_streak,
                    'progress' => $this->rewards->progress($request->user()),
                ],
            ]);
        } catch (RuntimeException $exception) {
            return $this->rewardError($exception->getMessage());
        }
    }

    public function redeem(Request $request): JsonResponse
    {
        try {
            $this->fraud->assertRedemptionAllowed($request->user(), $request);
            $result = $this->rewards->redeemTradingCredit($request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Trading credit redeemed.',
                'data' => [
                    'points_used' => $result['redemption']->points_used,
                    'usdt_value' => (float) $result['redemption']->usdt_value,
                    'credit_id' => $result['trading_credit']->id,
                    'credit_amount' => (float) $result['trading_credit']->amount,
                    'locked' => $result['trading_credit']->locked,
                    'expires_at' => $result['trading_credit']->expires_at?->toISOString(),
                    'available_points' => $result['points']->available_points,
                    'redeemed_points' => $result['points']->redeemed_points,
                ],
            ]);
        } catch (RuntimeException $exception) {
            return $this->rewardError($exception->getMessage());
        }
    }

    public function history(Request $request): JsonResponse
    {
        $checkins = DailyCheckin::query()
            ->where('user_id', $request->user()->id)
            ->latest('checkin_date')
            ->limit(30)
            ->get(['reward_points', 'streak_day', 'checkin_date', 'created_at']);

        $boxes = MysteryBox::query()
            ->where('user_id', $request->user()->id)
            ->latest('opened_at')
            ->limit(10)
            ->get(['reward_points', 'streak_cycle', 'opened_at']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'checkins' => $checkins,
                'mystery_boxes' => $boxes,
                'daily_probabilities' => config('checkin.daily_rewards'),
                'mystery_probabilities' => config('checkin.mystery_rewards'),
            ],
        ]);
    }

    private function rewardError(string $message): JsonResponse
    {
        $status = str_contains(strtolower($message), 'already') ? 409 : 422;

        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }
}
