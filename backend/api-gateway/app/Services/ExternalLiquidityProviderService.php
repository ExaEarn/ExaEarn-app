<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ExternalLiquidityProviderService
{
    public function fetchOrderBook(string $symbol, int $limit = 20): array
    {
        $url = rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/');
        $response = Http::timeout((int) config('sor.external_timeout_ms', 4000) / 1000)
            ->retry((int) config('sor.external_retry', 2), 150)
            ->get($url . '/api/v3/depth', ['symbol' => strtoupper($symbol), 'limit' => $limit]);

        if (!$response->ok()) {
            return ['bids' => [], 'asks' => []];
        }

        $json = $response->json();

        return [
            'bids' => collect($json['bids'] ?? [])->map(fn ($r) => ['price' => (string) ($r[0] ?? 0), 'amount' => (string) ($r[1] ?? 0)])->all(),
            'asks' => collect($json['asks'] ?? [])->map(fn ($r) => ['price' => (string) ($r[0] ?? 0), 'amount' => (string) ($r[1] ?? 0)])->all(),
        ];
    }

    public function fetchBestBidAsk(string $symbol): array
    {
        $book = $this->fetchOrderBook($symbol, 5);
        return [
            'best_bid' => isset($book['bids'][0]) ? (string) $book['bids'][0]['price'] : '0',
            'best_ask' => isset($book['asks'][0]) ? (string) $book['asks'][0]['price'] : '0',
            'liquidity_depth' => count($book['bids']) + count($book['asks']),
        ];
    }

    public function placeExternalOrder(array $order): array
    {
        if ((bool) config('services.binance.simulate', true)) {
            return [
                'status' => 'filled',
                'source' => 'binance',
                'executed_qty' => (string) ($order['quantity'] ?? '0'),
                'executed_price' => (string) ($order['price'] ?? '0'),
                'id' => 'sim-' . uniqid(),
            ];
        }

        $url = rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/');
        $res = Http::timeout((int) config('sor.external_timeout_ms', 4000) / 1000)
            ->retry((int) config('sor.external_retry', 2), 150)
            ->post($url . '/api/v3/order', $order);

        if (!$res->ok()) {
            return ['status' => 'failed', 'source' => 'binance'];
        }

        return ['status' => 'filled', 'source' => 'binance', 'raw' => $res->json()];
    }
}
