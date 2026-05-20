<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\MarketMakerLoopJob;
use App\Models\AIRiskAlert;
use App\Models\LiquidityPool;
use App\Models\MarketMakerConfig;
use App\Services\LiquidityPoolService;
use App\Services\MarketMakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketMakerAdminController extends Controller
{
    public function dashboard(MarketMakerService $maker): JsonResponse
    {
        $configs = MarketMakerConfig::query()->where('status', 'active')->orderBy('symbol')->get();

        $rows = $configs->map(function (MarketMakerConfig $cfg) use ($maker): array {
            $t = $maker->telemetryForSymbol($cfg->symbol);

            if (($t['status'] ?? '') !== 'ready') {
                return [
                    'symbol' => $cfg->symbol,
                    'status' => $t['status'] ?? 'unknown',
                    'spread_percent' => null,
                    'anchor_price' => null,
                    'volatility' => null,
                    'volume' => null,
                    'guard' => $t['guard'] ?? null,
                ];
            }

            return [
                'symbol' => $cfg->symbol,
                'status' => 'ready',
                'spread_percent' => $t['dynamic_spread_percent'],
                'anchor_price' => $t['anchor_price'],
                'base_price' => $t['base_price'],
                'volatility' => $t['volatility'],
                'volume' => $t['volume'],
                'guard' => $t['guard'],
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function configs(): JsonResponse
    {
        return response()->json(['data' => MarketMakerConfig::query()->orderBy('symbol')->get()]);
    }

    public function upsertConfig(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:40'],
            'spread_percentage' => ['required', 'numeric', 'min:0.01', 'max:10'],
            'order_size' => ['required', 'numeric', 'gt:0'],
            'max_orders' => ['required', 'integer', 'min:1', 'max:50'],
            'status' => ['required', 'in:active,paused'],
        ]);

        $cfg = MarketMakerConfig::query()->updateOrCreate(
            ['symbol' => strtoupper($payload['symbol'])],
            [
                'spread_percentage' => $payload['spread_percentage'],
                'order_size' => $payload['order_size'],
                'max_orders' => $payload['max_orders'],
                'status' => $payload['status'],
            ]
        );

        return response()->json(['message' => 'Config saved.', 'data' => $cfg]);
    }

    public function runSymbol(string $symbol, MarketMakerService $maker): JsonResponse
    {
        return response()->json(['data' => $maker->runForSymbol($symbol)]);
    }

    public function runLoop(): JsonResponse
    {
        MarketMakerLoopJob::dispatch();
        return response()->json(['message' => 'Market-maker loop queued.']);
    }

    public function pools(): JsonResponse
    {
        return response()->json(['data' => LiquidityPool::query()->orderBy('symbol')->get()]);
    }

    public function addLiquidity(Request $request, LiquidityPoolService $pools): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:40'],
            'base_asset_balance' => ['required', 'numeric', 'gt:0'],
            'quote_asset_balance' => ['required', 'numeric', 'gt:0'],
        ]);

        $row = $pools->addLiquidity(strtoupper($payload['symbol']), (string) $payload['base_asset_balance'], (string) $payload['quote_asset_balance']);

        return response()->json(['message' => 'Liquidity added.', 'data' => $row]);
    }

    public function removeLiquidity(Request $request, LiquidityPoolService $pools): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:40'],
            'base_asset_balance' => ['required', 'numeric', 'gt:0'],
            'quote_asset_balance' => ['required', 'numeric', 'gt:0'],
        ]);

        $row = $pools->removeLiquidity(strtoupper($payload['symbol']), (string) $payload['base_asset_balance'], (string) $payload['quote_asset_balance']);

        return response()->json(['message' => 'Liquidity removed.', 'data' => $row]);
    }

    public function alerts(): JsonResponse
    {
        return response()->json(['data' => AIRiskAlert::query()->latest('detected_at')->paginate(50)]);
    }
}
