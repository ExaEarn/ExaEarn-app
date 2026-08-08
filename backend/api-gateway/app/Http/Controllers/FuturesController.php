<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FuturesMarket;
use App\Models\FuturesOrder;
use App\Models\FuturesPosition;
use App\Models\FuturesTrade;
use App\Services\ConditionalOrderService;
use App\Services\CopyTradingService;
use App\Services\FuturesExecutionService;
use App\Services\FuturesOrderService;
use App\Services\MarginModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FuturesController extends Controller
{
    public function __construct(
        private readonly FuturesOrderService $orders,
        private readonly ConditionalOrderService $conditionalOrders,
        private readonly MarginModeService $marginModeService,
        private readonly CopyTradingService $copyTradingService,
        private readonly FuturesExecutionService $futuresExecutionService,
    )
    {
    }

    public function markets(): JsonResponse
    {
        $this->ensureDefaultMarkets();

        return response()->json(['data' => FuturesMarket::query()->orderBy('symbol')->get()]);
    }

    /**
     * Ensure the futures terminal always has a baseline supported market catalog.
     */
    private function ensureDefaultMarkets(): void
    {
        $symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'XRPUSDT', 'BNBUSDT', 'ADAUSDT', 'DOGEUSDT', 'TRXUSDT', 'DOTUSDT', 'AVAXUSDT', 'LINKUSDT', 'LTCUSDT', 'BCHUSDT', 'UNIUSDT', 'ATOMUSDT', 'MATICUSDT', '1000BONKUSDT', 'APTUSDT', 'ARBUSDT', 'SUIUSDT', 'OPUSDT', 'NEARUSDT', 'FILUSDT', 'ETCUSDT', 'XLMUSDT', 'HBARUSDT', 'AAVEUSDT', 'INJUSDT', 'SEIUSDT', 'TIAUSDT', 'WIFUSDT', 'PEPEUSDT'];
        $prices = [];
        $marketDataTimeout = max(0.5, (float) config('services.market_data.timeout_seconds', 1.5));
        $marketDataRetries = max(0, (int) config('services.market_data.retries', 0));

        try {
            $exchangeInfo = Http::timeout($marketDataTimeout)->retry($marketDataRetries, 100)->get('https://fapi.binance.com/fapi/v1/exchangeInfo');
            $tickers = Http::timeout($marketDataTimeout)->retry($marketDataRetries, 100)->get('https://fapi.binance.com/fapi/v1/ticker/24hr');

            if ($exchangeInfo->ok() && $tickers->ok()) {
                $activeSymbols = collect($exchangeInfo->json('symbols', []))
                    ->filter(function ($row): bool {
                        return is_array($row)
                            && ($row['status'] ?? null) === 'TRADING'
                            && ($row['contractType'] ?? null) === 'PERPETUAL'
                            && ($row['quoteAsset'] ?? null) === 'USDT'
                            && is_string($row['symbol'] ?? null);
                    })
                    ->keyBy(fn (array $row): string => strtoupper((string) $row['symbol']));

                $symbols = collect($tickers->json())
                    ->filter(fn ($row): bool => is_array($row) && isset($row['symbol']) && $activeSymbols->has(strtoupper((string) $row['symbol'])))
                    ->sortByDesc(fn ($row): float => (float) ($row['quoteVolume'] ?? 0))
                    ->take(40)
                    ->map(fn ($row): string => strtoupper((string) $row['symbol']))
                    ->values()
                    ->all();

                $prices = collect($tickers->json())
                    ->filter(fn ($row): bool => is_array($row) && isset($row['symbol']))
                    ->mapWithKeys(fn (array $row): array => [strtoupper((string) $row['symbol']) => (string) ($row['lastPrice'] ?? '0')])
                    ->all();
            }
        } catch (\Throwable) {
            $symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'XRPUSDT', 'BNBUSDT', 'ADAUSDT', 'DOGEUSDT', 'TRXUSDT', 'DOTUSDT', 'AVAXUSDT', 'LINKUSDT', 'LTCUSDT', 'BCHUSDT', 'UNIUSDT', 'ATOMUSDT', 'MATICUSDT', '1000BONKUSDT', 'APTUSDT', 'ARBUSDT', 'SUIUSDT', 'OPUSDT', 'NEARUSDT', 'FILUSDT', 'ETCUSDT', 'XLMUSDT', 'HBARUSDT', 'AAVEUSDT', 'INJUSDT', 'SEIUSDT', 'TIAUSDT', 'WIFUSDT', 'PEPEUSDT'];
            $prices = [];
        }

        if (empty($symbols)) {
            $symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'XRPUSDT', 'BNBUSDT', 'ADAUSDT', 'DOGEUSDT', 'TRXUSDT', 'DOTUSDT', 'AVAXUSDT', 'LINKUSDT', 'LTCUSDT', 'BCHUSDT', 'UNIUSDT', 'ATOMUSDT', 'MATICUSDT', '1000BONKUSDT', 'APTUSDT', 'ARBUSDT', 'SUIUSDT', 'OPUSDT', 'NEARUSDT', 'FILUSDT', 'ETCUSDT', 'XLMUSDT', 'HBARUSDT', 'AAVEUSDT', 'INJUSDT', 'SEIUSDT', 'TIAUSDT', 'WIFUSDT', 'PEPEUSDT'];
        }

        foreach ($symbols as $symbol) {
            FuturesMarket::query()->updateOrCreate(
                ['symbol' => $symbol],
                [
                    'status' => 'active',
                    'min_leverage' => 1,
                    'max_leverage' => 100,
                    'maintenance_margin_rate' => '0.00500000',
                    'last_price' => $prices[$symbol] ?? '0',
                ]
            );
        }
    }
    public function placeOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'type' => ['required', 'string', 'in:market,limit,stop-market,stop-limit,trailing-stop'],
            'side' => ['required', 'string', 'in:long,short'],
            'price' => ['nullable', 'numeric', 'gt:0'],
            'stop_price' => ['nullable', 'numeric', 'gt:0'],
            'trailing_distance' => ['nullable', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'leverage' => ['required', 'integer', 'min:1', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $order = $this->orders->placeOrder((int) $request->user()->id, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $order], 201);
    }

    public function cancelOrder(Request $request, string $orderUuid): JsonResponse
    {
        try {
            $order = $this->orders->cancelOrder((int) $request->user()->id, $orderUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $order]);
    }

    public function batchCancelOrders(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'order_uuids' => ['required', 'array', 'min:1', 'max:50'],
            'order_uuids.*' => ['required', 'string', 'uuid'],
        ]);

        try {
            $result = $this->orders->batchCancelOrders((int) $request->user()->id, $payload['order_uuids']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function orderDetails(Request $request, string $orderUuid): JsonResponse
    {
        try {
            $order = $this->orders->getOrderDetails((int) $request->user()->id, $orderUuid);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json(['data' => $order]);
    }

    public function marginStatus(Request $request): JsonResponse
    {
        try {
            $status = $this->orders->getUserMarginStatus((int) $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $status]);
    }

    public function validateOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'type' => ['required', 'string', 'in:market,limit,stop-market,stop-limit,trailing-stop'],
            'side' => ['required', 'string', 'in:long,short'],
            'price' => ['nullable', 'numeric', 'gt:0'],
            'stop_price' => ['nullable', 'numeric', 'gt:0'],
            'trailing_distance' => ['nullable', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'leverage' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $validation = $this->orders->canPlaceOrder((int) $request->user()->id, $payload);

        return response()->json(['data' => $validation]);
    }

    public function openOrders(Request $request): JsonResponse
    {
        $query = FuturesOrder::query()->where('user_id', $request->user()->id)->latest();
        if ($request->filled('symbol')) {
            $query->where('symbol', strtoupper((string) $request->query('symbol')));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 50))]);
    }

    public function positions(Request $request): JsonResponse
    {
        $query = FuturesPosition::query()->where('user_id', $request->user()->id)->latest();
        if ($request->filled('symbol')) {
            $query->where('symbol', strtoupper((string) $request->query('symbol')));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 50))]);
    }

    public function trades(Request $request): JsonResponse
    {
        $query = FuturesTrade::query()->latest('executed_at');
        if ($request->filled('symbol')) {
            $query->where('symbol', strtoupper((string) $request->query('symbol')));
        }

        return response()->json(['data' => $query->limit((int) $request->query('limit', 100))->get()]);
    }

    public function createConditionalOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'type' => ['required', 'string', 'in:stop_loss,take_profit'],
            'trigger_order_type' => ['required', 'string', 'in:market,limit'],
            'trigger_price' => ['required', 'numeric', 'gt:0'],
            'execution_price' => ['nullable', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'position_id' => ['nullable', 'integer', 'exists:futures_positions,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $order = $this->conditionalOrders->createConditionalOrder((int) $request->user()->id, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $order], 201);
    }

    public function triggerConditionals(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'market_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $triggered = $this->conditionalOrders->triggerPendingOrders((string) $payload['symbol'], (string) $payload['market_price']);
        $advanced = $this->orders->processTriggeredOrders((string) $payload['symbol'], (string) $payload['market_price']);

        return response()->json(['data' => ['conditional_triggered' => count($triggered), 'advanced_triggered' => $advanced]]);
    }

    public function setMarginType(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'position_id' => ['required', 'integer', 'exists:futures_positions,id'],
            'margin_type' => ['required', 'string', 'in:cross,isolated'],
        ]);

        try {
            $position = $this->marginModeService->setMarginType((int) $request->user()->id, (int) $payload['position_id'], (string) $payload['margin_type']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $position]);
    }

    public function followTrader(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'trader_id' => ['required', 'integer', 'exists:traders,id'],
            'amount_allocated' => ['required', 'numeric', 'gt:0'],
            'risk_level' => ['nullable', 'string', 'in:low,medium,high'],
        ]);

        try {
            $relationship = $this->copyTradingService->followTrader(
                (int) $request->user()->id,
                (int) $payload['trader_id'],
                (float) $payload['amount_allocated'],
                (string) ($payload['risk_level'] ?? 'medium')
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $relationship], 201);
    }

    public function unfollowTrader(Request $request, int $traderId): JsonResponse
    {
        try {
            $this->copyTradingService->unfollowTrader((int) $request->user()->id, $traderId);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Copy relationship removed.']);
    }

    public function marketTick(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'mark_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $result = $this->futuresExecutionService->onMarketTick((string) $payload['symbol'], (string) $payload['mark_price']);
        return response()->json(['data' => $result]);
    }
}



