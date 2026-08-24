<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperProject;
use App\Models\DeveloperSandboxBalance;
use App\Models\ExaAiSession;
use App\Models\FuturesMarket;
use App\Models\FuturesOrder;
use App\Models\FuturesPosition;
use App\Models\MarginAccount;
use App\Models\MarginLoan;
use App\Models\StakingPosition;
use App\Models\Order;
use App\Models\Swap;
use App\Services\MarketDataService;
use App\Services\DeveloperRealtimeService;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DeveloperApiController extends Controller
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly TradeService $tradeService,
        private readonly DeveloperRealtimeService $realtime,
    ) {
    }

    public function exchangeInfo(): JsonResponse
    {
        return $this->ok(['symbols' => $this->marketData->symbols(), 'timezone' => 'UTC', 'server_time' => now()->toISOString()]);
    }

    public function markets(): JsonResponse
    {
        return $this->ok($this->marketData->symbols());
    }

    public function apiStatus(): JsonResponse
    {
        return $this->ok([
            'products' => config('developer_api.api_status', []),
            'available_permissions' => config('developer_api.permissions', []),
            'websocket_topics' => config('developer_api.websocket.allowed_topics', []),
            'webhook_events' => config('developer_api.webhooks.events', []),
        ]);
    }

    public function tickers(): JsonResponse
    {
        return $this->ok($this->marketData->tickers());
    }

    public function ticker(string $symbol): JsonResponse
    {
        return $this->ok($this->marketData->ticker($symbol));
    }

    public function orderBook(Request $request, string $symbol): JsonResponse
    {
        return $this->ok($this->marketData->orderBook($symbol, (int) $request->query('limit', 50)));
    }

    public function trades(Request $request, string $symbol): JsonResponse
    {
        return $this->ok($this->marketData->recentTrades($symbol, (int) $request->query('limit', 100)));
    }

    public function klines(Request $request, string $symbol): JsonResponse
    {
        return $this->ok($this->marketData->candles($symbol, (string) $request->query('interval', '1m'), (int) $request->query('limit', 500)));
    }

    public function balances(Request $request): JsonResponse
    {
        $project = $request->attributes->get('developer_project');
        if ($project instanceof DeveloperProject && $project->environment === 'sandbox') {
            return $this->ok(DeveloperSandboxBalance::query()
                ->where('project_id', $project->id)
                ->orderBy('asset')
                ->get()
                ->map(fn (DeveloperSandboxBalance $balance): array => [
                    'asset' => $balance->asset,
                    'available' => (string) $balance->available,
                    'reserved' => (string) $balance->reserved,
                    'total' => bcadd((string) $balance->available, (string) $balance->reserved, 8),
                    'environment' => 'sandbox',
                ])
                ->all());
        }

        return $this->ok($request->user()->wallets()->orderBy('currency')->get()->map(fn ($wallet): array => [
            'asset' => $wallet->currency,
            'available' => (string) $wallet->available_balance,
            'locked' => (string) $wallet->locked_balance,
            'total' => (string) $wallet->total_balance,
            'environment' => 'production',
        ])->all());
    }

    public function realtimeSession(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $payload = $request->validate([
            'topics' => ['required', 'array', 'min:1'],
            'topics.*' => ['required', 'string', 'max:120'],
        ]);

        try {
            return $this->ok($this->realtime->createSession($project, $payload['topics']));
        } catch (RuntimeException $exception) {
            return $this->error('WEBSOCKET_TOPIC_REJECTED', $exception->getMessage(), 422);
        }
    }

    public function realtimeReplay(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $payload = $request->validate([
            'stream' => ['required', 'string', 'max:120'],
            'after_sequence' => ['required', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return $this->ok($this->realtime->replay($project, (string) $payload['stream'], (int) $payload['after_sequence'], (int) ($payload['limit'] ?? 500)));
    }

    public function futuresMarkets(): JsonResponse
    {
        return $this->ok(FuturesMarket::query()->orderBy('symbol')->get());
    }

    public function futuresOrders(Request $request): JsonResponse
    {
        return $this->ok(FuturesOrder::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function futuresPositions(Request $request): JsonResponse
    {
        return $this->ok(FuturesPosition::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function marginAccounts(Request $request): JsonResponse
    {
        return $this->ok(MarginAccount::query()->where('user_id', $request->user()->id)->latest()->get());
    }

    public function marginLoans(Request $request): JsonResponse
    {
        return $this->ok(MarginLoan::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function convertHistory(Request $request): JsonResponse
    {
        return $this->ok(Swap::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function stakingPositions(Request $request): JsonResponse
    {
        return $this->ok(class_exists(StakingPosition::class)
            ? StakingPosition::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->query('per_page', 25))
            : []);
    }

    public function exaAiSessions(Request $request): JsonResponse
    {
        return $this->ok(ExaAiSession::query()->where('user_id', $request->user()->id)->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function createSpotOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:40'],
            'side' => ['required', 'string', 'in:buy,sell'],
            'type' => ['required', 'string', 'in:market,limit,stop_loss,take_profit'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'price' => ['nullable', 'numeric', 'gt:0'],
            'client_order_id' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $result = $this->tradeService->placeOrder(
                (int) $request->user()->id,
                $this->normalizeSymbol((string) $payload['symbol']),
                (string) $payload['side'],
                (string) $payload['type'],
                (string) $payload['quantity'],
                isset($payload['price']) ? (string) $payload['price'] : null,
                [
                    'client_order_id' => $payload['client_order_id'] ?? null,
                    'source' => 'developer_api',
                    'request_id' => $request->attributes->get('request_id'),
                ]
            );
        } catch (RuntimeException $exception) {
            return $this->error('ORDER_REJECTED', $exception->getMessage(), 422);
        }

        return $this->ok($this->orderPayload($result['order']), 201);
    }

    public function getSpotOrder(Request $request, string $orderId): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_uuid', $orderId)
            ->first();

        if (! $order) {
            return $this->error('ORDER_NOT_FOUND', 'Order not found.', 404);
        }

        return $this->ok($this->orderPayload($order));
    }

    private function normalizeSymbol(string $symbol): string
    {
        return str_replace('-', '/', strtoupper($symbol));
    }

    private function project(Request $request): DeveloperProject
    {
        $project = $request->attributes->get('developer_project');
        if (! $project instanceof DeveloperProject) {
            throw new RuntimeException('Developer project context is unavailable.');
        }

        return $project;
    }

    private function orderPayload(Order $order): array
    {
        return [
            'order_id' => $order->order_uuid,
            'client_order_id' => $order->client_order_id,
            'symbol' => str_replace('/', '-', (string) $order->pair),
            'side' => $order->side,
            'type' => $order->type,
            'price' => (string) $order->price,
            'quantity' => (string) $order->amount,
            'executed_quantity' => (string) $order->filled_amount,
            'status' => $order->status,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'timestamp' => now()->timestamp], $status);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => request()->attributes->get('request_id'),
            ],
        ], $status);
    }
}
