<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FuturesMarket;
use App\Models\FuturesOrder;
use App\Models\FuturesTrade;
use App\Models\InternalAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

class FuturesOrderService
{
    private const SCALE = 8;

    public function __construct(
        private readonly BlockchainService $blockchain,
        private readonly FuturesRiskEngineService $riskEngine,
    )
    {
    }

    public function placeOrder(int $userId, array $payload): FuturesOrder
    {
        $symbol = strtoupper((string) $payload['symbol']);
        $type = strtolower((string) $payload['type']);
        $side = strtolower((string) $payload['side']);
        $quantity = (string) $payload['quantity'];
        $leverage = (int) $payload['leverage'];
        $price = isset($payload['price']) ? (string) $payload['price'] : null;

        if (!in_array($type, ['market', 'limit', 'stop-market', 'stop-limit', 'trailing-stop'], true)) {
            throw new RuntimeException('Invalid order type.');
        }

        if (!in_array($side, ['long', 'short'], true)) {
            throw new RuntimeException('Invalid order side.');
        }

        return DB::transaction(function () use ($userId, $symbol, $type, $side, $quantity, $leverage, $price, $payload): FuturesOrder {
            $market = FuturesMarket::query()->where('symbol', $symbol)->lockForUpdate()->firstOrFail();
            if ($market->status !== 'active') {
                throw new RuntimeException('Futures market is not active.');
            }

            $minLev = max((int) config('futures.min_leverage', 1), (int) $market->min_leverage);
            $maxLev = min((int) config('futures.max_leverage', 100), (int) $market->max_leverage);
            if ($leverage < $minLev || $leverage > $maxLev) {
                throw new RuntimeException('Leverage out of allowed range.');
            }

            $isConditional = in_array($type, ['stop-market', 'stop-limit', 'trailing-stop'], true);
            $executionPrice = $type === 'market' || $isConditional
                ? (string) $market->last_price
                : (string) $price;

            if ($this->compare($executionPrice, '0') <= 0) {
                throw new RuntimeException('Invalid execution price.');
            }

            $notional = $this->mul($executionPrice, $quantity);
            $marginRequired = $this->div($notional, (string) $leverage);
            $this->riskEngine->validateOrderRisk($userId, $market, $side, $leverage, $notional, $marginRequired);
            $this->validateMargin($userId, $marginRequired);

            $this->lockMargin($userId, $marginRequired, sprintf('futures_margin_lock:%s', $symbol));

            $order = FuturesOrder::query()->create([
                'order_uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'futures_market_id' => $market->id,
                'symbol' => $symbol,
                'type' => $type,
                'side' => $side,
                'price' => $type === 'limit' ? $executionPrice : null,
                'quantity' => $quantity,
                'leverage' => $leverage,
                'notional_value' => $notional,
                'initial_margin' => $marginRequired,
                'filled_quantity' => '0',
                'remaining_quantity' => $quantity,
                'status' => 'open',
                'source' => (string) ($payload['source'] ?? 'api'),
                'metadata' => $payload['metadata'] ?? null,
            ]);

            if ($isConditional) {
                $order->status = 'pending_trigger';
                $order->metadata = array_merge($order->metadata ?? [], [
                    'stop_price' => (string) ($payload['stop_price'] ?? ''),
                    'trailing_distance' => (string) ($payload['trailing_distance'] ?? ''),
                    'triggered' => false,
                ]);
                $order->save();
            } else {
                try {
                    $match = $this->blockchain->submitFuturesOrder([
                        'order_uuid' => $order->order_uuid,
                        'user_id' => $userId,
                        'symbol' => $symbol,
                        'type' => $type,
                        'side' => $side,
                        'price' => $executionPrice,
                        'quantity' => $quantity,
                        'created_at' => now()->toISOString(),
                    ]);

                    $this->applyMatchResult($order, $match);
                } catch (\Throwable $exception) {
                    // Keep order open for retry if matching service is unavailable.
                    $order->metadata = array_merge($order->metadata ?? [], [
                        'matching_error' => $exception->getMessage(),
                    ]);
                    $order->save();
                }
            }

            $this->publishOrderEvent('futures.order.placed', $order->toArray());
            $this->logAudit($userId, 'futures_order_placed', [
                'order_uuid' => $order->order_uuid,
                'symbol' => $symbol,
                'margin_required' => $marginRequired,
            ]);

            return $order;
        });
    }

    public function cancelOrder(int $userId, string $orderUuid): FuturesOrder
    {
        return DB::transaction(function () use ($userId, $orderUuid): FuturesOrder {
            $order = FuturesOrder::query()
                ->where('order_uuid', $orderUuid)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($order->status, ['open', 'partially_filled'], true)) {
                throw new RuntimeException('Only open futures orders can be cancelled.');
            }

            try {
                $this->blockchain->cancelFuturesOrder([
                    'symbol' => $order->symbol,
                    'order_uuid' => $order->order_uuid,
                ]);
            } catch (\Throwable) {
                // continue local cancellation
            }

            $remainingRatio = $this->compare((string) $order->quantity, '0') > 0
                ? $this->div((string) $order->remaining_quantity, (string) $order->quantity)
                : '0';
            $toRelease = $this->mul((string) $order->initial_margin, $remainingRatio);
            if ($this->compare($toRelease, '0') > 0) {
                $this->releaseMargin($userId, $toRelease, sprintf('futures_margin_release:%s', $order->symbol));
            }

            $order->status = 'cancelled';
            $order->save();

            $this->publishOrderEvent('futures.order.cancelled', $order->toArray());
            $this->logAudit($userId, 'futures_order_cancelled', ['order_uuid' => $orderUuid]);

            return $order;
        });
    }

    public function batchCancelOrders(int $userId, array $orderUuids): array
    {
        $cancelledOrders = [];
        $failedOrders = [];

        foreach ($orderUuids as $orderUuid) {
            try {
                $order = $this->cancelOrder($userId, $orderUuid);
                $cancelledOrders[] = $order;
            } catch (\Throwable $exception) {
                $failedOrders[] = [
                    'order_uuid' => $orderUuid,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'cancelled' => $cancelledOrders,
            'failed' => $failedOrders,
        ];
    }

    public function getOrderDetails(int $userId, string $orderUuid): FuturesOrder
    {
        return FuturesOrder::query()
            ->where('order_uuid', $orderUuid)
            ->where('user_id', $userId)
            ->with('market', 'user')
            ->firstOrFail();
    }

    public function getUserMarginStatus(int $userId): array
    {
        $account = InternalAccount::query()
            ->where('user_id', $userId)
            ->where('account_type', 'futures_wallet')
            ->first();

        if (!$account) {
            throw new RuntimeException('Futures wallet not found for user.');
        }

        $totalMargin = $this->add((string) $account->available_balance, (string) $account->locked_balance);
        $usagePercentage = $this->compare($totalMargin, '0') > 0
            ? $this->mul($this->div((string) $account->locked_balance, $totalMargin), '100')
            : '0';

        return [
            'total_margin' => $totalMargin,
            'available_margin' => (string) $account->available_balance,
            'locked_margin' => (string) $account->locked_balance,
            'margin_usage_percentage' => $usagePercentage,
        ];
    }

    public function calculateMarginRequired(string $price, string $quantity, int $leverage): string
    {
        $notional = $this->mul($price, $quantity);
        return $this->div($notional, (string) $leverage);
    }

    public function canPlaceOrder(int $userId, array $payload): array
    {
        $symbol = strtoupper((string) $payload['symbol']);
        $quantity = (string) $payload['quantity'];
        $leverage = (int) $payload['leverage'];
        $price = isset($payload['price']) ? (string) $payload['price'] : null;
        $type = strtolower((string) $payload['type']);

        $errors = [];

        // Validate symbol exists
        $market = FuturesMarket::query()->where('symbol', $symbol)->first();
        if (!$market) {
            $errors[] = 'Market not found for symbol.';
            return [
                'can_place' => false,
                'errors' => $errors,
                'data' => null,
            ];
        }

        if ($market->status !== 'active') {
            $errors[] = 'Market is not active.';
        }

        // Validate leverage
        $minLev = max((int) config('futures.min_leverage', 1), (int) $market->min_leverage);
        $maxLev = min((int) config('futures.max_leverage', 100), (int) $market->max_leverage);
        if ($leverage < $minLev || $leverage > $maxLev) {
            $errors[] = "Leverage must be between {$minLev} and {$maxLev}.";
        }

        // Get execution price
        $executionPrice = $type === 'market' ? (string) $market->last_price : $price;
        if (!$executionPrice || $this->compare($executionPrice, '0') <= 0) {
            $errors[] = 'Invalid price for order execution.';
        }

        // Calculate margin
        $marginRequired = $this->calculateMarginRequired($executionPrice ?? '0', $quantity, $leverage);

        // Check margin availability
        try {
            $this->validateMargin($userId, $marginRequired);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }

        return [
            'can_place' => count($errors) === 0,
            'errors' => $errors,
            'data' => [
                'symbol' => $symbol,
                'execution_price' => $executionPrice,
                'quantity' => $quantity,
                'leverage' => $leverage,
                'notional_value' => $this->mul($executionPrice ?? '0', $quantity),
                'margin_required' => $marginRequired,
            ],
        ];
    }

    public function validateMargin(int $userId, string $requiredMargin): void
    {
        $account = InternalAccount::query()
            ->where('user_id', $userId)
            ->where('account_type', 'futures_wallet')
            ->lockForUpdate()
            ->first();

        if (!$account) {
            throw new RuntimeException('Futures wallet account not found.');
        }

        if ($this->compare((string) $account->available_balance, $requiredMargin) < 0) {
            throw new RuntimeException('Insufficient futures margin balance.');
        }
    }

    private function lockMargin(int $userId, string $amount, string $reference): void
    {
        $account = InternalAccount::query()
            ->where('user_id', $userId)
            ->where('account_type', 'futures_wallet')
            ->lockForUpdate()
            ->firstOrFail();

        $account->available_balance = $this->sub((string) $account->available_balance, $amount);
        $account->locked_balance = $this->add((string) $account->locked_balance, $amount);
        $account->save();

        $this->recordInternalWalletTx($userId, 'lock', 'futures_wallet', 'USDT', $amount, $reference, 'Lock margin for futures order');
    }

    private function releaseMargin(int $userId, string $amount, string $reference): void
    {
        $account = InternalAccount::query()
            ->where('user_id', $userId)
            ->where('account_type', 'futures_wallet')
            ->lockForUpdate()
            ->firstOrFail();

        if ($this->compare((string) $account->locked_balance, $amount) < 0) {
            throw new RuntimeException('Insufficient locked futures margin.');
        }

        $account->locked_balance = $this->sub((string) $account->locked_balance, $amount);
        $account->available_balance = $this->add((string) $account->available_balance, $amount);
        $account->save();

        $this->recordInternalWalletTx($userId, 'release', 'futures_wallet', 'USDT', $amount, $reference, 'Release margin from cancelled order');
    }

    private function recordInternalWalletTx(int $userId, string $type, string $walletType, string $asset, string $amount, string $reference, string $description): void
    {
        DB::table('internal_wallet_transactions')->insert([
            'user_id' => $userId,
            'type' => $type,
            'wallet_type' => $walletType,
            'asset' => $asset,
            'amount' => $amount,
            'reference' => $reference,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function applyMatchResult(FuturesOrder $order, array $match): void
    {
        $trades = is_array($match['trades'] ?? null) ? $match['trades'] : [];
        if ($trades === []) {
            return;
        }

        $filled = '0';
        foreach ($trades as $trade) {
            $qty = $this->fmt((string) ($trade['quantity'] ?? '0'));
            $price = $this->fmt((string) ($trade['price'] ?? '0'));
            $filled = $this->add($filled, $qty);

            FuturesTrade::query()->create([
                'futures_market_id' => $order->futures_market_id,
                'buy_order_id' => (int) FuturesOrder::query()->where('order_uuid', (string) ($trade['buy_order_id'] ?? ''))->value('id'),
                'sell_order_id' => (int) FuturesOrder::query()->where('order_uuid', (string) ($trade['sell_order_id'] ?? ''))->value('id'),
                'symbol' => $order->symbol,
                'price' => $price,
                'quantity' => $qty,
                'notional_value' => $this->mul($price, $qty),
                'metadata' => ['source' => 'node_matching'],
                'executed_at' => now(),
            ]);
        }

        $remaining = $this->sub((string) $order->quantity, $filled);
        $order->filled_quantity = $filled;
        $order->remaining_quantity = $remaining;
        $order->status = $this->compare($remaining, '0') <= 0 ? 'filled' : 'partially_filled';
        $order->save();
    }

    public function processTriggeredOrders(string $symbol, string $marketPrice): int
    {
        $orders = FuturesOrder::query()
            ->where('symbol', strtoupper($symbol))
            ->where('status', 'pending_trigger')
            ->get();

        $triggered = 0;
        foreach ($orders as $order) {
            $stopPrice = (string) data_get($order->metadata, 'stop_price', '0');
            $trailingDistance = (string) data_get($order->metadata, 'trailing_distance', '0');
            $shouldTrigger = false;

            if ($order->type === 'trailing-stop') {
                $reference = (string) data_get($order->metadata, 'trailing_reference', $marketPrice);
                $newRef = $order->side === 'long'
                    ? max((float) $reference, (float) $marketPrice)
                    : min((float) $reference, (float) $marketPrice);
                $order->metadata = array_merge($order->metadata ?? [], ['trailing_reference' => (string) $newRef]);
                $derivedStop = $order->side === 'long'
                    ? $this->sub((string) $newRef, $trailingDistance === '' ? '0' : $trailingDistance)
                    : $this->add((string) $newRef, $trailingDistance === '' ? '0' : $trailingDistance);
                $stopPrice = $derivedStop;
                $order->metadata = array_merge($order->metadata ?? [], ['stop_price' => $stopPrice]);
                $order->save();
            }

            if ($order->side === 'long') {
                $shouldTrigger = $this->compare($marketPrice, $stopPrice) <= 0;
            } else {
                $shouldTrigger = $this->compare($marketPrice, $stopPrice) >= 0;
            }

            if (!$shouldTrigger) {
                continue;
            }

            $order->status = 'triggered';
            $order->metadata = array_merge($order->metadata ?? [], ['triggered' => true, 'triggered_at' => now()->toISOString()]);
            $order->save();
            $triggered++;
        }

        return $triggered;
    }

    private function fmt(string $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }

    private function publishOrderEvent(string $event, array $data): void
    {
        try {
            Redis::publish((string) config('futures.stream_channel', 'futures_updates'), json_encode([
                'event' => $event,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ], JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            // non-fatal
        }
    }

    private function logAudit(int $userId, string $action, array $metadata = []): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float) $a + (float) $b, self::SCALE, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float) $a - (float) $b, self::SCALE, '.', '');
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, self::SCALE) : number_format((float) $a * (float) $b, self::SCALE, '.', '');
    }

    private function div(string $a, string $b): string
    {
        if ($this->compare($b, '0') === 0) {
            throw new RuntimeException('Division by zero.');
        }

        return function_exists('bcdiv') ? bcdiv($a, $b, self::SCALE) : number_format((float) $a / (float) $b, self::SCALE, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        $af = (float) $a;
        $bf = (float) $b;
        return $af <=> $bf;
    }
}

