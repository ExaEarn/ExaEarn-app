<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Order;
use App\Services\FinancialDecimal;
use RuntimeException;

class SpotMatchingEngine
{
    /**
     * @param array<int, Order> $restingOrders
     * @return array{fills: array<int, array<string, mixed>>, remaining:string, action:string, reject_reason:?string}
     */
    public function match(Order $incoming, array $restingOrders, array $options = []): array
    {
        $remaining = FinancialDecimal::normalize((string) $incoming->remaining_amount);
        $fills = [];
        $isBuy = $incoming->side === 'buy';
        $type = strtolower((string) $incoming->type);
        $timeInForce = strtoupper((string) ($incoming->time_in_force ?: 'GTC'));
        $postOnly = (bool) $incoming->post_only;
        $marketProtectionBps = FinancialDecimal::normalize((string) ($options['market_protection_bps'] ?? '500'));

        $book = $this->sortRestingOrders($restingOrders, $isBuy);
        $marketable = $this->firstMarketable($incoming, $book) !== null;

        if ($postOnly && $marketable) {
            return ['fills' => [], 'remaining' => $remaining, 'action' => 'reject', 'reject_reason' => 'Post-only order would take liquidity.'];
        }

        if ($timeInForce === 'FOK' && !$this->canFillCompletely($incoming, $book, $marketProtectionBps)) {
            return ['fills' => [], 'remaining' => $remaining, 'action' => 'cancel', 'reject_reason' => 'FOK order cannot be fully filled.'];
        }

        foreach ($book as $resting) {
            if (FinancialDecimal::compare($remaining, '0') <= 0) {
                break;
            }

            if ((int) $incoming->user_id === (int) $resting->user_id) {
                return ['fills' => $fills, 'remaining' => $remaining, 'action' => 'cancel', 'reject_reason' => 'Self-trade prevention cancelled newest order.'];
            }

            if (!$this->isMarketable($incoming, $resting, $marketProtectionBps)) {
                break;
            }

            $quantity = FinancialDecimal::min($remaining, (string) $resting->remaining_amount);
            if (FinancialDecimal::compare($quantity, '0') <= 0) {
                continue;
            }

            $price = FinancialDecimal::normalize((string) $resting->price);
            $fills[] = [
                'maker_order_id' => $resting->id,
                'maker_order_uuid' => $resting->order_uuid,
                'maker_user_id' => $resting->user_id,
                'taker_order_id' => $incoming->id,
                'taker_order_uuid' => $incoming->order_uuid,
                'taker_user_id' => $incoming->user_id,
                'maker_side' => $resting->side,
                'taker_side' => $incoming->side,
                'price' => $price,
                'quantity' => $quantity,
                'quote_amount' => FinancialDecimal::mul($quantity, $price, 18),
            ];

            $remaining = FinancialDecimal::sub($remaining, $quantity);
        }

        $action = match (true) {
            FinancialDecimal::compare($remaining, '0') <= 0 => 'filled',
            $type === 'market' || in_array($timeInForce, ['IOC', 'FOK'], true) => 'cancel_remainder',
            default => 'rest',
        };

        return [
            'fills' => $fills,
            'remaining' => $remaining,
            'action' => $action,
            'reject_reason' => null,
        ];
    }

    /**
     * @param array<int, Order> $restingOrders
     * @return array<int, Order>
     */
    private function sortRestingOrders(array $restingOrders, bool $incomingIsBuy): array
    {
        usort($restingOrders, function (Order $a, Order $b) use ($incomingIsBuy): int {
            $priceCompare = FinancialDecimal::compare((string) $a->price, (string) $b->price);
            if ($priceCompare !== 0) {
                return $incomingIsBuy ? $priceCompare : -$priceCompare;
            }

            $sequenceCompare = ((int) $a->sequence) <=> ((int) $b->sequence);
            if ($sequenceCompare !== 0) {
                return $sequenceCompare;
            }

            return ((int) $a->id) <=> ((int) $b->id);
        });

        return $restingOrders;
    }

    /**
     * @param array<int, Order> $book
     */
    private function firstMarketable(Order $incoming, array $book): ?Order
    {
        foreach ($book as $resting) {
            if ($this->isMarketable($incoming, $resting, '500')) {
                return $resting;
            }
        }

        return null;
    }

    /**
     * @param array<int, Order> $book
     */
    private function canFillCompletely(Order $incoming, array $book, string $marketProtectionBps): bool
    {
        $remaining = FinancialDecimal::normalize((string) $incoming->remaining_amount);
        foreach ($book as $resting) {
            if ((int) $incoming->user_id === (int) $resting->user_id) {
                return false;
            }
            if (!$this->isMarketable($incoming, $resting, $marketProtectionBps)) {
                break;
            }
            $remaining = FinancialDecimal::sub($remaining, FinancialDecimal::min($remaining, (string) $resting->remaining_amount));
            if (FinancialDecimal::compare($remaining, '0') <= 0) {
                return true;
            }
        }

        return false;
    }

    private function isMarketable(Order $incoming, Order $resting, string $marketProtectionBps): bool
    {
        if ($incoming->side === $resting->side) {
            throw new RuntimeException('Cannot match orders on the same side.');
        }

        $type = strtolower((string) $incoming->type);
        if ($type === 'market') {
            return $this->passesMarketProtection($incoming, $resting, $marketProtectionBps);
        }

        return $incoming->side === 'buy'
            ? FinancialDecimal::compare((string) $resting->price, (string) $incoming->price) <= 0
            : FinancialDecimal::compare((string) $resting->price, (string) $incoming->price) >= 0;
    }

    private function passesMarketProtection(Order $incoming, Order $resting, string $marketProtectionBps): bool
    {
        $reference = FinancialDecimal::normalize((string) data_get($incoming->metadata, 'reference_price', '0'));
        if (FinancialDecimal::compare($reference, '0') <= 0) {
            return true;
        }

        $band = FinancialDecimal::div(FinancialDecimal::mul($reference, $marketProtectionBps), '10000');
        $limit = $incoming->side === 'buy'
            ? FinancialDecimal::add($reference, $band)
            : FinancialDecimal::sub($reference, $band);

        return $incoming->side === 'buy'
            ? FinancialDecimal::compare((string) $resting->price, $limit) <= 0
            : FinancialDecimal::compare((string) $resting->price, $limit) >= 0;
    }
}
