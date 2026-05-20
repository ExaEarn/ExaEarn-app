<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Market;
use App\Models\Order;
use App\Models\OrderBook;

class OrderBookDepthService
{
    public function updateSnapshot(Market $market): void
    {
        $bids = Order::query()
            ->where('market_id', $market->id)
            ->where('side', 'buy')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderByDesc('price')->orderBy('created_at')->limit(100)
            ->get(['price', 'remaining_amount'])
            ->map(fn (Order $o) => ['price' => (string) $o->price, 'amount' => (string) $o->remaining_amount])
            ->values()->all();

        $asks = Order::query()
            ->where('market_id', $market->id)
            ->where('side', 'sell')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('price')->orderBy('created_at')->limit(100)
            ->get(['price', 'remaining_amount'])
            ->map(fn (Order $o) => ['price' => (string) $o->price, 'amount' => (string) $o->remaining_amount])
            ->values()->all();

        OrderBook::query()->updateOrCreate(
            ['market_id' => $market->id, 'pair' => $market->symbol],
            ['bid_orders' => $bids, 'ask_orders' => $asks, 'last_synced_at' => now()]
        );
    }

    public function generateDepthLevels(float $anchorPrice, float $spreadPercent, float $orderSize, int $maxOrders): array
    {
        $levels = ['bids' => [], 'asks' => []];
        $step = $spreadPercent / 100;

        for ($i = 1; $i <= $maxOrders; $i++) {
            $factor = $step * $i;
            $levels['bids'][] = [
                'price' => round($anchorPrice * (1 - $factor), 8),
                'amount' => round($orderSize * $i, 8),
            ];
            $levels['asks'][] = [
                'price' => round($anchorPrice * (1 + $factor), 8),
                'amount' => round($orderSize * $i, 8),
            ];
        }

        return $levels;
    }
}
