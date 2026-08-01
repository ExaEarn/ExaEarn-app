<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\Order;
use App\Models\Trade;
use App\Services\SmartOrderRoutingService;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TradeController extends Controller
{
    public function __construct(
        private readonly TradeService $tradeService,
        private readonly SmartOrderRoutingService $sor,
    )
    {
    }

    public function markets(): JsonResponse
    {
        return response()->json(['data' => $this->tradeService->listMarkets()]);
    }

    public function createMarket(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'base_currency' => ['required', 'string', 'max:16'],
            'quote_currency' => ['required', 'string', 'max:16'],
            'status' => ['nullable', 'string', 'max:20'],
            'last_price' => ['nullable', 'numeric', 'gte:0'],
            'price_precision' => ['nullable', 'numeric', 'gt:0'],
            'min_order_size' => ['nullable', 'numeric', 'gte:0'],
            'max_order_size' => ['nullable', 'numeric', 'gte:0'],
            'maker_fee' => ['nullable', 'numeric', 'gte:0'],
            'taker_fee' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $market = Market::query()->updateOrCreate(
            ['symbol' => strtoupper((string) $payload['symbol'])],
            [
                'base_currency' => strtoupper((string) $payload['base_currency']),
                'quote_currency' => strtoupper((string) $payload['quote_currency']),
                'status' => $payload['status'] ?? 'active',
                'last_price' => $payload['last_price'] ?? 0,
                'price_precision' => $payload['price_precision'] ?? 0.0001,
                'min_order_size' => $payload['min_order_size'] ?? 0,
                'max_order_size' => $payload['max_order_size'] ?? 0,
                'maker_fee' => $payload['maker_fee'] ?? 0,
                'taker_fee' => $payload['taker_fee'] ?? 0,
            ]
        );

        return response()->json(['data' => $market], 201);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'pair' => ['required', 'string', 'max:32'],
            'side' => ['required', 'string', 'in:buy,sell'],
            'type' => ['required', 'string', 'in:market,limit,stop_loss,take_profit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'price' => ['nullable', 'numeric', 'gt:0'],
            'stop_price' => ['nullable', 'numeric', 'gt:0'],
            'trigger_order_type' => ['nullable', 'string', 'in:market,limit'],
            'smart_routing' => ['nullable', 'boolean'],
            'max_slippage' => ['nullable', 'numeric', 'gt:0', 'lte:5'],
            'metadata' => ['nullable', 'array'],
        ]);

        $isConditional = in_array((string) $payload['type'], ['stop_loss', 'take_profit'], true);
        if ($isConditional && !isset($payload['stop_price'])) {
            return response()->json(['message' => 'stop_price is required for conditional orders.'], 422);
        }

        if (
            $isConditional
            && (($payload['trigger_order_type'] ?? 'market') === 'limit')
            && !isset($payload['price'])
        ) {
            return response()->json(['message' => 'price is required for conditional limit orders.'], 422);
        }

        try {
            if (($payload['smart_routing'] ?? false) === true && (string) $payload['type'] === 'market') {
                $result = $this->sor->routeOrder((int) $request->user()->id, [
                    'pair' => (string) $payload['pair'],
                    'side' => (string) $payload['side'],
                    'amount' => (string) $payload['amount'],
                    'max_slippage' => isset($payload['max_slippage']) ? (string) $payload['max_slippage'] : null,
                ]);

                return response()->json(['data' => $result], 201);
            }

            $result = $this->tradeService->placeOrder(
                (int) $request->user()->id,
                (string) $payload['pair'],
                (string) $payload['side'],
                (string) $payload['type'],
                (string) $payload['amount'],
                isset($payload['price']) ? (string) $payload['price'] : null,
                array_merge($payload['metadata'] ?? [], [
                    'stop_price' => isset($payload['stop_price']) ? (string) $payload['stop_price'] : null,
                    'trigger_order_type' => $payload['trigger_order_type'] ?? null,
                ])
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }

    public function cancelOrder(Request $request, string $orderUuid): JsonResponse
    {
        try {
            $order = $this->tradeService->cancelOrder((int) $request->user()->id, $orderUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $order]);
    }

    public function swap(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'from_currency' => ['required', 'string', 'max:16'],
            'to_currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->tradeService->swap(
                (int) $request->user()->id,
                (string) $payload['from_currency'],
                (string) $payload['to_currency'],
                (string) $payload['amount'],
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }

    public function openOrders(Request $request): JsonResponse
    {
        $query = Order::query()->where('user_id', $request->user()->id)->latest();
        if ($request->filled('pair')) {
            $query->where('pair', $this->normalizePair((string) $request->query('pair')));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 50))]);
    }

    public function userTrades(Request $request): JsonResponse
    {
        $query = Trade::query()
            ->with(['buyOrder', 'sellOrder'])
            ->where(function ($builder) use ($request): void {
                $builder
                    ->whereHas('buyOrder', fn ($orderQuery) => $orderQuery->where('user_id', $request->user()->id))
                    ->orWhereHas('sellOrder', fn ($orderQuery) => $orderQuery->where('user_id', $request->user()->id));
            })
            ->latest('executed_at');

        if ($request->filled('pair')) {
            $query->where('pair', $this->normalizePair((string) $request->query('pair')));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 50))]);
    }

    public function orderBook(string $pair): JsonResponse
    {
        return response()->json(['data' => $this->tradeService->getOrderBook($this->normalizePair($pair), 50)]);
    }

    public function orderBookByQuery(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'pair' => ['required', 'string', 'max:32'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json([
            'data' => $this->tradeService->getOrderBook(
                $this->normalizePair((string) $payload['pair']),
                (int) ($payload['limit'] ?? 50)
            ),
        ]);
    }

    public function trades(string $pair): JsonResponse
    {
        return response()->json(['data' => $this->tradeService->getRecentTrades($this->normalizePair($pair), 100)]);
    }

    public function tradesByQuery(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'pair' => ['required', 'string', 'max:32'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json([
            'data' => $this->tradeService->getRecentTrades(
                $this->normalizePair((string) $payload['pair']),
                (int) ($payload['limit'] ?? 100)
            ),
        ]);
    }

    public function candles(Request $request, string $pair): JsonResponse
    {
        return response()->json([
            'data' => $this->tradeService->getCandles(
                $this->normalizePair($pair),
                (string) $request->query('timeframe', '1m'),
                (int) $request->query('limit', 100)
            ),
        ]);
    }

    public function candlesByQuery(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'pair' => ['required', 'string', 'max:32'],
            'timeframe' => ['nullable', 'string', 'max:8'],
            'interval' => ['nullable', 'string', 'max:8'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        return response()->json([
            'data' => $this->tradeService->getCandles(
                $this->normalizePair((string) $payload['pair']),
                (string) ($payload['timeframe'] ?? $payload['interval'] ?? '1m'),
                (int) ($payload['limit'] ?? 200)
            ),
        ]);
    }

    public function klines(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'interval' => ['nullable', 'string', 'max:8'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $candles = $this->tradeService->getCandles(
            $this->normalizePair((string) $payload['symbol']),
            (string) ($payload['interval'] ?? '1m'),
            (int) ($payload['limit'] ?? 500)
        );

        return response()->json([
            'data' => array_map(static fn (array $candle): array => [
                'time' => (int) ($candle['timestamp'] ?? 0),
                'open' => (string) ($candle['open'] ?? '0'),
                'high' => (string) ($candle['high'] ?? '0'),
                'low' => (string) ($candle['low'] ?? '0'),
                'close' => (string) ($candle['close'] ?? '0'),
                'volume' => (string) ($candle['volume'] ?? '0'),
            ], $candles),
        ]);
    }

    private function normalizePair(string $pair): string
    {
        $clean = strtoupper(trim($pair));

        if (str_contains($clean, '/')) {
            return $clean;
        }

        if (str_contains($clean, '-')) {
            [$base, $quote] = array_pad(explode('-', $clean, 2), 2, 'USDT');
            return sprintf('%s/%s', trim($base), trim($quote));
        }

        $quotes = ['USDT', 'USDC', 'BTC', 'ETH'];
        foreach ($quotes as $quote) {
            if (str_ends_with($clean, $quote) && strlen($clean) > strlen($quote)) {
                return sprintf('%s/%s', substr($clean, 0, -strlen($quote)), $quote);
            }
        }

        return $clean;
    }
}
