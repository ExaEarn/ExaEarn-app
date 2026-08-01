<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FlightGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlightGameAdminController extends Controller
{
    public function __construct(private readonly FlightGameService $flightGame)
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
        ]);

        return response()->json([
            'data' => $this->flightGame->updateSettings($payload['settings'], (int) $request->user()->id),
        ]);
    }

    public function tick(): JsonResponse
    {
        return response()->json(['data' => $this->flightGame->tick()]);
    }
}
