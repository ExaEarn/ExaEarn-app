<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\Order;
use RuntimeException;

class PreTradeValidationService
{
    public function __construct(private readonly InstrumentService $instruments)
    {
    }

    /**
     * @return array{market:Market, side:string, type:string, quantity:string, price:string, time_in_force:string, post_only:bool, client_order_id:?string}
     */
    public function validateNewOrder(int $userId, array $command): array
    {
        $market = $this->instruments->market((string) $command['pair']);
        $this->instruments->assertTradable($market);

        $side = strtolower((string) $command['side']);
        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new RuntimeException('Invalid order side.');
        }

        $type = strtolower((string) $command['type']);
        if (!in_array($type, ['limit', 'market'], true)) {
            throw new RuntimeException('Order type is not supported by the Phase 2 spot engine.');
        }

        $timeInForce = strtoupper((string) ($command['time_in_force'] ?? 'GTC'));
        if (!in_array($timeInForce, ['GTC', 'IOC', 'FOK'], true)) {
            throw new RuntimeException('Unsupported time in force.');
        }

        if ($type === 'market' && $timeInForce === 'GTC') {
            $timeInForce = 'IOC';
        }

        $postOnly = (bool) ($command['post_only'] ?? false);
        if ($postOnly && $type !== 'limit') {
            throw new RuntimeException('Post-only is only supported for limit orders.');
        }
        if ($postOnly && $timeInForce !== 'GTC') {
            throw new RuntimeException('Post-only requires GTC time in force.');
        }

        $quantity = $this->instruments->assertQuantity($market, (string) $command['amount']);
        $price = $this->instruments->assertPrice($market, $command['price'] ?? null, $type);
        $this->instruments->assertNotional($market, $quantity, $price);

        $clientOrderId = isset($command['client_order_id']) && trim((string) $command['client_order_id']) !== ''
            ? trim((string) $command['client_order_id'])
            : null;

        if ($clientOrderId !== null) {
            $existing = Order::query()
                ->where('user_id', $userId)
                ->where('market_id', $market->id)
                ->where('client_order_id', $clientOrderId)
                ->first();

            if ($existing) {
                throw new RuntimeException('Duplicate client_order_id.');
            }
        }

        return [
            'market' => $market,
            'side' => $side,
            'type' => $type,
            'quantity' => $quantity,
            'price' => $price,
            'time_in_force' => $timeInForce,
            'post_only' => $postOnly,
            'client_order_id' => $clientOrderId,
        ];
    }

    public function existingClientOrder(int $userId, string $pair, ?string $clientOrderId): ?Order
    {
        if ($clientOrderId === null || trim($clientOrderId) === '') {
            return null;
        }

        $market = $this->instruments->market($pair);

        return Order::query()
            ->where('user_id', $userId)
            ->where('market_id', $market->id)
            ->where('client_order_id', trim($clientOrderId))
            ->first();
    }
}
