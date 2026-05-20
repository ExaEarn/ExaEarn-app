<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesConditionalOrder;
use App\Models\FuturesMarket;
use App\Models\FuturesPosition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

class ConditionalOrderService
{
    private const SCALE = 8;

    public function __construct(
        private readonly FuturesPositionService $positionService,
        private readonly FuturesOrderService $orderService,
    ) {
    }

    public function createConditionalOrder(int $userId, array $payload): FuturesConditionalOrder
    {
        $symbol = strtoupper((string) $payload['symbol']);
        $type = strtolower((string) $payload['type']);
        $triggerOrderType = strtolower((string) ($payload['trigger_order_type'] ?? 'market'));
        $triggerPrice = (string) $payload['trigger_price'];
        $executionPrice = isset($payload['execution_price']) ? (string) $payload['execution_price'] : null;
        $quantity = (string) $payload['quantity'];
        $positionId = isset($payload['position_id']) ? (int) $payload['position_id'] : null;

        if (!in_array($type, ['stop_loss', 'take_profit'], true)) {
            throw new RuntimeException('Invalid conditional order type.');
        }

        if (!in_array($triggerOrderType, ['market', 'limit'], true)) {
            throw new RuntimeException('Invalid trigger order type.');
        }

        if ($this->compare($triggerPrice, '0') <= 0) {
            throw new RuntimeException('Trigger price must be greater than zero.');
        }

        if ($this->compare($quantity, '0') <= 0) {
            throw new RuntimeException('Conditional order quantity must be greater than zero.');
        }

        $market = FuturesMarket::query()->where('symbol', $symbol)->firstOrFail();

        $position = $this->resolvePosition($userId, $symbol, $positionId);
        if (!$position || $position->status !== 'open') {
            throw new RuntimeException('Open futures position not found for this symbol.');
        }

        return DB::transaction(function () use ($userId, $market, $position, $symbol, $type, $triggerOrderType, $triggerPrice, $executionPrice, $quantity): FuturesConditionalOrder {
            return FuturesConditionalOrder::query()->create([
                'conditional_uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'futures_position_id' => $position->id,
                'futures_market_id' => $position->futures_market_id,
                'symbol' => $symbol,
                'type' => $type,
                'trigger_order_type' => $triggerOrderType,
                'trigger_price' => $triggerPrice,
                'execution_price' => $executionPrice,
                'quantity' => $quantity,
                'status' => 'pending',
                'source' => 'api',
                'metadata' => $payload['metadata'] ?? null,
            ]);
        });
    }

    public function listUserConditionalOrders(int $userId): Collection
    {
        return FuturesConditionalOrder::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function triggerPendingOrders(string $symbol, string $marketPrice): array
    {
        $orders = FuturesConditionalOrder::query()
            ->where('symbol', strtoupper($symbol))
            ->where('status', 'pending')
            ->get();

        $triggered = [];
        foreach ($orders as $order) {
            if (!$this->meetsTrigger($order, $marketPrice)) {
                continue;
            }

            $order->status = 'triggered';
            $order->execution_price = $order->trigger_order_type === 'limit' ? $order->execution_price ?? $order->trigger_price : $marketPrice;
            try {
                $this->orderService->placeOrder((int) $order->user_id, [
                    'symbol' => $order->symbol,
                    'type' => $order->trigger_order_type,
                    'side' => $order->position?->side === 'long' ? 'short' : 'long',
                    'price' => $order->trigger_order_type === 'limit' ? (string) $order->execution_price : null,
                    'quantity' => (string) $order->quantity,
                    'leverage' => (int) ($order->position?->leverage ?? 1),
                    'source' => 'conditional',
                    'metadata' => ['conditional_order_id' => $order->id],
                ]);
                $order->status = 'executed';
            } catch (\Throwable $exception) {
                $order->status = 'failed';
                $order->metadata = array_merge($order->metadata ?? [], ['execution_error' => $exception->getMessage()]);
            }
            $order->save();

            try {
                Redis::publish((string) config('futures.stream_channel', 'futures_updates'), json_encode([
                    'event' => 'futures.conditional.triggered',
                    'data' => ['conditional_uuid' => $order->conditional_uuid, 'symbol' => $order->symbol, 'status' => $order->status],
                    'timestamp' => now()->toISOString(),
                ], JSON_THROW_ON_ERROR));
            } catch (\Throwable) {
            }

            $triggered[] = $order;
        }

        return $triggered;
    }

    public function cancelByPosition(int $positionId): int
    {
        return FuturesConditionalOrder::query()
            ->where('futures_position_id', $positionId)
            ->whereIn('status', ['pending', 'triggered'])
            ->update(['status' => 'cancelled']);
    }

    private function resolvePosition(int $userId, string $symbol, ?int $positionId): ?FuturesPosition
    {
        if ($positionId !== null) {
            return FuturesPosition::query()
                ->where('id', $positionId)
                ->where('user_id', $userId)
                ->where('symbol', $symbol)
                ->first();
        }

        return FuturesPosition::query()
            ->where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('status', 'open')
            ->first();
    }

    private function meetsTrigger(FuturesConditionalOrder $order, string $marketPrice): bool
    {
        $marketPrice = (string) $marketPrice;
        if ($order->type === 'stop_loss') {
            return $this->compare($marketPrice, (string) $order->trigger_price) <= 0;
        }

        return $this->compare($marketPrice, (string) $order->trigger_price) >= 0;
    }

    private function compare(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float) $a <=> (float) $b);
    }
}
