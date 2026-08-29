<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FlightGameService;
use App\Services\GameReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlightGameAdminController extends Controller
{
    public function __construct(
        private readonly FlightGameService $flightGame,
        private readonly GameReconciliationService $reconciliation,
    )
    {
    }

    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->flightGame->adminSummary()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'settings' => ['required', 'array'],
            'settings.enabled_assets' => ['sometimes', 'array'],
            'settings.default_asset' => ['sometimes', 'string', 'max:16'],
            'settings.min_stake' => ['sometimes', 'numeric', 'gt:0'],
            'settings.max_stake' => ['sometimes', 'numeric', 'gt:0'],
            'settings.max_multiplier' => ['sometimes', 'numeric', 'gt:1'],
            'settings.betting_window_seconds' => ['sometimes', 'integer', 'min:3', 'max:60'],
            'settings.growth_rate' => ['sometimes', 'numeric', 'gt:0'],
            'settings.public_seed' => ['sometimes', 'string', 'max:64'],
            'settings.product_classification' => ['sometimes', 'string', 'in:ENTERTAINMENT_ONLY,FREE_TO_PLAY,REWARD_BASED,PROMOTIONAL,SKILL_BASED,REAL_MONEY_GAMING,REGULATED_GAMBLING'],
            'settings.game_mode' => ['sometimes', 'string', 'in:sandbox,real,hybrid'],
            'settings.public_real_money_enabled' => ['sometimes', 'boolean'],
            'settings.legal_real_money_approved' => ['sometimes', 'boolean'],
            'settings.minimum_kyc_level' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'settings.jurisdiction_required' => ['sometimes', 'boolean'],
            'settings.minimum_age_by_country' => ['sometimes', 'array'],
            'settings.daily_participation_limit' => ['sometimes', 'numeric', 'gte:0'],
            'settings.weekly_participation_limit' => ['sometimes', 'numeric', 'gte:0'],
            'settings.monthly_participation_limit' => ['sometimes', 'numeric', 'gte:0'],
            'settings.daily_loss_limit' => ['sometimes', 'numeric', 'gte:0'],
            'settings.weekly_loss_limit' => ['sometimes', 'numeric', 'gte:0'],
            'settings.monthly_loss_limit' => ['sometimes', 'numeric', 'gte:0'],
            'settings.treasury_required_reserve' => ['sometimes', 'numeric', 'gte:0'],
            'settings.max_round_liability' => ['sometimes', 'numeric', 'gte:0'],
            'settings.max_platform_exposure' => ['sometimes', 'numeric', 'gte:0'],
        ]);

        if (($payload['settings']['public_real_money_enabled'] ?? false) === true || ($payload['settings']['legal_real_money_approved'] ?? false) === true) {
            return response()->json([
                'message' => 'EXA Flight real-money activation requires maker-checker approval and external legal readiness.',
            ], 422);
        }

        return response()->json([
            'data' => $this->flightGame->updateSettings($payload['settings'], (int) $request->user()->id),
        ]);
    }

    public function tick(): JsonResponse
    {
        return response()->json(['data' => $this->flightGame->tick()]);
    }

    public function control(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string', 'in:PAUSE_NEW_ENTRIES,DISABLE_REAL_MONEY,RESUME_DEMO_MODE,CANCEL_ROUND'],
            'round_uuid' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'max:240'],
        ]);

        $action = strtoupper((string) $payload['action']);
        $actor = (int) $request->user()->id;

        if ($action === 'CANCEL_ROUND') {
            return response()->json(['data' => $this->flightGame->cancelRound((string) $payload['round_uuid'], $actor, (string) $payload['reason'])]);
        }

        $settings = match ($action) {
            'PAUSE_NEW_ENTRIES' => ['max_stake' => '0.00000000'],
            'DISABLE_REAL_MONEY' => ['public_real_money_enabled' => false, 'legal_real_money_approved' => false, 'game_mode' => 'sandbox'],
            'RESUME_DEMO_MODE' => ['game_mode' => 'sandbox', 'public_real_money_enabled' => false],
            default => [],
        };

        return response()->json(['data' => $this->flightGame->updateSettings($settings, $actor)]);
    }

    public function reconciliation(): JsonResponse
    {
        return response()->json(['data' => $this->reconciliation->run()]);
    }
}
