<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalOrder;
use App\Models\ExternalVenueBalance;
use App\Models\LiquidityRoutePlan;
use App\Models\LiquiditySource;
use App\Models\Market;
use App\Models\MarketMakerAccount;
use App\Models\MarketMakerQuote;
use App\Models\TreasuryLiquidityBucket;
use App\Services\Liquidity\ConsolidatedLiquidityBookService;
use App\Services\Liquidity\LiquidityLoadProbeService;
use App\Services\Liquidity\LiquidityOperationalReadinessService;
use App\Services\Liquidity\LiquidityReconciliationService;
use App\Services\Liquidity\LiquiditySourceRegistry;
use App\Services\Liquidity\MarketMakingEngineService;
use App\Services\Liquidity\NetExposureService;
use App\Services\Liquidity\SmartOrderRouter;
use App\Services\Liquidity\TreasuryInventoryService;
use App\Services\Liquidity\TreasuryRebalancingService;
use App\Services\Liquidity\WithdrawalLiquidityReserveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LiquidityOperationsController extends Controller
{
    public function overview(LiquidityOperationalReadinessService $readiness, TreasuryInventoryService $treasury): JsonResponse
    {
        return response()->json([
            'data' => [
                'readiness' => $readiness->check(),
                'sources' => LiquiditySource::query()->orderBy('code')->get(),
                'treasury_inventory' => $treasury->snapshot(),
                'route_plans' => LiquidityRoutePlan::query()->latest()->limit(20)->get(),
                'external_orders' => ExternalOrder::query()->latest()->limit(20)->get(),
                'active_market_maker_quotes' => MarketMakerQuote::query()->where('status', 'ACTIVE')->count(),
            ],
        ]);
    }

    public function readiness(LiquidityOperationalReadinessService $readiness): JsonResponse
    {
        return response()->json(['data' => $readiness->check()]);
    }

    public function sources(LiquiditySourceRegistry $registry): JsonResponse
    {
        $registry->syncConfiguredSources();

        return response()->json(['data' => LiquiditySource::query()->orderBy('code')->get()]);
    }

    public function sourceHealth(string $source, LiquiditySourceRegistry $registry): JsonResponse
    {
        return response()->json(['data' => $registry->adapter($source)->healthCheck()]);
    }

    public function consolidatedBook(Request $request, string $symbol, ConsolidatedLiquidityBookService $books): JsonResponse
    {
        return response()->json([
            'data' => $books->build($symbol, (int) $request->integer('limit', 20)),
        ]);
    }

    public function planRoute(Request $request, SmartOrderRouter $router): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'symbol' => ['required', 'string'],
            'side' => ['required', 'in:buy,sell'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'order_type' => ['nullable', 'string'],
            'limit_price' => ['nullable', 'numeric', 'gt:0'],
            'idempotency_key' => ['nullable', 'string', 'max:160'],
        ]);

        $market = Market::query()->where('symbol', strtoupper((string) $data['symbol']))->firstOrFail();
        $plan = $router->plan($market, (int) $data['user_id'], (string) $data['side'], (string) $data['quantity'], [
            'order_type' => $data['order_type'] ?? 'market',
            'limit_price' => $data['limit_price'] ?? null,
            'parent_reference' => 'admin-sor:' . ($data['idempotency_key'] ?? Str::uuid()),
            'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
        ]);

        return response()->json(['data' => $plan]);
    }

    public function treasuryInventory(TreasuryInventoryService $treasury): JsonResponse
    {
        return response()->json(['data' => $treasury->snapshot()]);
    }

    public function allocateBucket(Request $request, TreasuryInventoryService $treasury): JsonResponse
    {
        $data = $request->validate([
            'asset' => ['required', 'string', 'max:24'],
            'bucket' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'gte:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json([
            'data' => $treasury->allocateBucket((string) $data['asset'], (string) $data['bucket'], (string) $data['amount'], [
                'reason' => $data['reason'] ?? null,
            ]),
        ]);
    }

    public function withdrawalReserve(string $asset, WithdrawalLiquidityReserveService $reserve): JsonResponse
    {
        return response()->json(['data' => $reserve->calculate($asset)]);
    }

    public function netExposure(string $asset, NetExposureService $exposure): JsonResponse
    {
        return response()->json(['data' => $exposure->calculate($asset)]);
    }

    public function rebalance(string $asset, TreasuryRebalancingService $rebalancing): JsonResponse
    {
        return response()->json(['data' => $rebalancing->evaluate($asset)]);
    }

    public function marketMakerQuote(Request $request, MarketMakingEngineService $marketMaking): JsonResponse
    {
        $data = $request->validate([
            'market_maker_id' => ['required', 'integer', 'exists:market_maker_accounts,id'],
            'symbol' => ['required', 'string'],
            'reference_price' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $market = Market::query()->where('symbol', strtoupper((string) $data['symbol']))->firstOrFail();
        $account = MarketMakerAccount::query()->findOrFail((int) $data['market_maker_id']);

        return response()->json(['data' => $marketMaking->quote($market, $account, (string) $data['reference_price'], (string) $data['quantity'])]);
    }

    public function cancelUnsafeMarketMakerQuotes(MarketMakingEngineService $marketMaking): JsonResponse
    {
        return response()->json(['data' => ['cancelled' => $marketMaking->cancelUnsafe('admin_phase8_safety')]]);
    }

    public function venueBalances(): JsonResponse
    {
        return response()->json(['data' => ExternalVenueBalance::query()->with('account')->orderBy('asset')->get()]);
    }

    public function reconciliation(LiquidityReconciliationService $reconciliation): JsonResponse
    {
        return response()->json(['data' => $reconciliation->run()]);
    }

    public function loadProbe(Request $request, LiquidityLoadProbeService $probe): JsonResponse
    {
        return response()->json(['data' => $probe->run((int) $request->integer('iterations', 100))]);
    }
}
