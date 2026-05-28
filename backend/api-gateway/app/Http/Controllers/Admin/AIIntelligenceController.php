<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AIOptimizationLoopJob;
use App\Jobs\CollectMarketDataJob;
use App\Models\AIDecisionLog;
use App\Models\AIRiskAlert;
use App\Models\AISystemOverride;
use App\Models\MarketData;
use App\Services\AdaptiveMarketMakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIIntelligenceController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:40'],
            'price' => ['required', 'numeric'],
            'volume' => ['required', 'numeric'],
            'spread' => ['required', 'numeric'],
            'volatility' => ['nullable', 'numeric'],
            'timestamp' => ['nullable', 'date'],
        ]);

        CollectMarketDataJob::dispatch($payload);

        return response()->json(['message' => 'Market data queued.'], 202);
    }

    public function dashboard(Request $request, AdaptiveMarketMakerService $marketMaker): JsonResponse
    {
        $symbols = array_filter(array_map('trim', (array) config('ai_intel.symbols', [])));

        $marketTrends = collect($symbols)->map(function (string $symbol) use ($marketMaker): array {
            $latest = MarketData::query()->where('symbol', $symbol)->latest('timestamp')->first();
            return [
                'symbol' => $symbol,
                'latest_price' => $latest?->price,
                'latest_volume' => $latest?->volume,
                'latest_spread' => $latest?->spread,
                'mm_params' => $marketMaker->current($symbol),
            ];
        })->values();

        return response()->json([
            'data' => [
                'market_trends' => $marketTrends,
                'ai_decisions' => AIDecisionLog::query()->latest('decided_at')->limit(20)->get(),
                'liquidity_health' => AIDecisionLog::query()->where('decision_type', 'smart_liquidity')->latest('decided_at')->limit(20)->get(),
                'risk_alerts' => AIRiskAlert::query()->latest('detected_at')->limit(20)->get(),
                'override' => AISystemOverride::query()->where('enabled', true)->latest()->first(),
            ],
        ]);
    }

    public function alerts(): JsonResponse
    {
        return response()->json(['data' => AIRiskAlert::query()->latest('detected_at')->paginate(50)]);
    }

    public function override(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['nullable', 'string', 'max:40'],
            'enabled' => ['required', 'boolean'],
            'params' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $override = AISystemOverride::query()->create([
            'symbol' => isset($payload['symbol']) ? strtoupper((string) $payload['symbol']) : null,
            'enabled' => (bool) $payload['enabled'],
            'params' => $payload['params'] ?? null,
            'set_by' => $request->user()?->id,
            'expires_at' => $payload['expires_at'] ?? null,
        ]);

        return response()->json(['message' => 'Override saved.', 'data' => $override]);
    }

    public function runLoop(): JsonResponse
    {
        AIOptimizationLoopJob::dispatch();

        return response()->json(['message' => 'AI optimization loop queued.']);
    }
}
