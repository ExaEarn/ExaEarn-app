<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FuturesMarket;
use App\Models\FuturesOrder;
use App\Models\FuturesTrade;
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
        private readonly ReservationService $reservations,
        private readonly FuturesMarginService $marginService,
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
        $timeInForce = strtoupper((string) ($payload['time_in_force'] ?? 'GTC'));
        $reduceOnly = (bool) ($payload['reduce_only'] ?? false);
        $postOnly = (bool) ($payload['post_only'] ?? false);

        if (!in_array($type, ['market', 'limit', 'stop-market', 'stop-limit', 'trailing-stop'], true)) {
            throw new RuntimeException('Invalid order type.');
        }

        if (!in_array($timeInForce, ['GTC', 'IOC', 'FOK'], true)) {
            throw new RuntimeException('Invalid time in force.');
        }

        if ($postOnly && $type !== 'limit') {
            throw new RuntimeException('Post-only is supported only for limit orders.');
        }

        if (!in_array($side, ['long', 'short'], true)) {
            throw new RuntimeException('Invalid order side.');
        }

        return DB::transaction(function () use ($postOnly, $reduceOnly, $timeInForce, $userId, $symbol, $type, $side, $quantity, $leverage, $price, $payload): FuturesOrder {
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

            app(TradingRiskEngine::class)->assertOrderAllowed($userId, 'futures', $market, [
                'symbol' => $market->symbol,
                'side' => $side,
                'type' => $type,
                'quantity' => $quantity,
                'price' => $executionPrice,
                'leverage' => $leverage,
                'reduce_only' => $reduceOnly,
            ]);

            if ($postOnly) {
                $reference = (string) ($market->mark_price ?: $market->last_price);
                if ($this->compare($reference, '0') > 0 && (($side === 'long' && $this->compare($executionPrice, $reference) >= 0) || ($side === 'short' && $this->compare($executionPrice, $reference) <= 0))) {
                    throw new RuntimeException('Post-only futures order would take liquidity.');
                }
            }

            $notional = $this->marginService->notional($executionPrice, $quantity);
            $marginRequired = $this->marginService->initialMargin($market, $notional, $leverage);
            $this->riskEngine->validateOrderRisk($userId, $market, $side, $leverage, $notional, $marginRequired, [
                'price' => $executionPrice,
                'quantity' => $quantity,
                'reduce_only' => $reduceOnly,
                'margin_mode' => (string) ($payload['margin_mode'] ?? 'cross'),
            ]);
            $orderUuid = (string) Str::uuid();
            $reservation = $this->reservations->reserveUserAccount(
                $userId, 'futures', (string) ($market->settlement_asset ?: 'USDT'), $marginRequired, 'futures_initial_margin',
                'futures_order', $orderUuid, 'futures-order:' . $orderUuid,
                ['product' => 'futures', 'symbol' => $symbol, 'margin_mode' => (string) ($payload['margin_mode'] ?? 'cross'), 'leverage' => $leverage]
            );

            $order = FuturesOrder::query()->create([
                'order_uuid' => $orderUuid,
                'client_order_id' => $payload['client_order_id'] ?? null,
                'user_id' => $userId,
                'futures_market_id' => $market->id,
                'symbol' => $symbol,
                'type' => $type,
                'time_in_force' => $timeInForce,
                'side' => $side,
                'reduce_only' => $reduceOnly,
                'post_only' => $postOnly,
                'price' => $type === 'limit' ? $executionPrice : null,
                'trigger_price' => $payload['stop_price'] ?? null,
                'trigger_source' => strtoupper((string) ($payload['trigger_source'] ?? 'MARK')),
                'quantity' => $quantity,
                'leverage' => $leverage,
                'notional_value' => $notional,
                'initial_margin' => $marginRequired,
                'filled_quantity' => '0',
                'remaining_quantity' => $quantity,
                'status' => 'open',
                'source' => (string) ($payload['source'] ?? 'api'),
                'metadata' => array_merge($payload['metadata'] ?? [], ['reservation_id' => $reservation->reservation_id, 'margin_mode' => (string) ($payload['margin_mode'] ?? 'cross')]),
            ]);

            if ($isConditional) {
                $order->status = 'pending_trigger';
                $order->metadata = array_merge($order->metadata ?? [], [
                    'stop_price' => (string) ($payload['stop_price'] ?? ''),
                    'trailing_distance' => (string) ($payload['trailing_distance'] ?? ''),
                    'triggered' => false,
                ]);
                $order->save();
            } elseif (filter_var(config('futures.allow_external_execution', false), FILTER_VALIDATE_BOOL)) {
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
            } else {
                $order->metadata = array_merge($order->metadata ?? [], [
                    'engine_mode' => (string) config('futures.engine_mode', 'legacy'),
                    'execution_authority' => 'exaearn_internal_pending',
                    'external_execution_disabled' => true,
                ]);
                $order->save();
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

            if ($order->status === 'cancelled') {
                return $order;
            }
            if (!in_array($order->status, ['open', 'partially_filled', 'pending_trigger'], true)) {
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

            $reservationId = (string) data_get($order->metadata, 'reservation_id', '');
            if ($reservationId !== '') {
                $reservation = \App\Models\Reservation::query()->where('reservation_id', $reservationId)->first();
                if ($reservation && in_array($reservation->status, [\App\Models\Reservation::STATUS_ACTIVE, \App\Models\Reservation::STATUS_PARTIALLY_CONSUMED], true)) {
                    $this->reservations->release($reservationId, null, ['event' => 'futures_order_cancel', 'order_uuid' => $orderUuid]);
                }
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
        $account = app(LedgerService::class)->getOrCreateAccount($userId, 'futures', 'USDT');
        return array_merge(
            app(BalanceProjectionService::class)->accountProjection($account),
            ['cross_margin' => app(CrossMarginHealthService::class)->health($userId, 'USDT')]
        );
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
        $side = strtolower((string) ($payload['side'] ?? ''));

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
        $notional = $this->marginService->notional($executionPrice ?? '0', $quantity);
        $marginRequired = $this->marginService->initialMargin($market, $notional, $leverage);
        try {
            $this->riskEngine->validateOrderRisk($userId, $market, $side, $leverage, $notional, $marginRequired, [
                'price' => $executionPrice ?? '0',
                'quantity' => $quantity,
                'reduce_only' => (bool) ($payload['reduce_only'] ?? false),
                'margin_mode' => (string) ($payload['margin_mode'] ?? 'cross'),
            ]);
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
                'notional_value' => $notional,
                'margin_required' => $marginRequired,
            ],
        ];
    }

    public function validateMargin(int $userId, string $requiredMargin): void
    {
        $account = app(LedgerService::class)->getOrCreateAccount($userId, 'futures', 'USDT');
        $available = app(BalanceProjectionService::class)->accountProjection($account)['available'];
        if ($this->compare($available, $requiredMargin) < 0) {
            throw new RuntimeException('Insufficient futures margin balance.');
        }
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
        $reservationId = (string) data_get($order->metadata, 'reservation_id', '');
        if ($reservationId !== '' && $this->compare($filled, '0') > 0) {
            $fillRatio = $this->div($filled, (string) $order->quantity);
            $this->reservations->consume($reservationId, $this->mul((string) $order->initial_margin, $fillRatio), ['event' => 'futures_partial_execution', 'order_uuid' => $order->order_uuid]);
        }
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
                    ? ($this->compare($reference, $marketPrice) >= 0 ? $reference : $marketPrice)
                    : ($this->compare($reference, $marketPrice) <= 0 ? $reference : $marketPrice);
                $order->metadata = array_merge($order->metadata ?? [], ['trailing_reference' => $newRef]);
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
        return FinancialDecimal::normalize($value, self::SCALE);
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
        return FinancialDecimal::add($a, $b, self::SCALE);
    }

    private function sub(string $a, string $b): string
    {
        return FinancialDecimal::sub($a, $b, self::SCALE);
    }

    private function mul(string $a, string $b): string
    {
        return FinancialDecimal::mul($a, $b, self::SCALE);
    }

    private function div(string $a, string $b): string
    {
        if ($this->compare($b, '0') === 0) {
            throw new RuntimeException('Division by zero.');
        }

        return FinancialDecimal::div($a, $b, self::SCALE);
    }

    private function compare(string $a, string $b): int
    {
        return FinancialDecimal::compare($a, $b, self::SCALE);
    }
}

