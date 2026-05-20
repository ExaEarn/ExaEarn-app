<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CampaignEngineService;
use App\Services\CampaignBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignEngineService $campaignEngineService,
        private readonly CampaignBroadcastService $campaignBroadcastService
    ) {
    }

    public function generate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'string', 'in:NFT_MINTED,USER_BEHAVIOR,PARTNERSHIP'],
            'count' => ['nullable', 'integer', 'min:0'],
            'timeframe' => ['nullable', 'string', 'max:20'],
            'action' => ['nullable', 'string', 'max:32'],
            'trend' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'max:32'],
            'partner_name' => ['nullable', 'string', 'max:120'],
            'impact' => ['nullable', 'string', 'max:255'],
            'benefit_to_users' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $campaign = $this->campaignEngineService->generate($payload);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->campaignBroadcastService->publish($campaign, [
            'type' => $payload['type'],
            'input' => $payload,
        ]);

        return response()->json(['data' => $campaign]);
    }
}
