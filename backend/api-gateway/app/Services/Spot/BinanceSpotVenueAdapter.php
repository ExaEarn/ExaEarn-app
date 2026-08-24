<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Services\ExternalLiquidityProviderService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BinanceSpotVenueAdapter implements ExternalSpotVenue
{
    public function __construct(private readonly ExternalLiquidityProviderService $liquidityProvider)
    {
    }

    public function venueCode(): string
    {
        return 'BINANCE';
    }

    public function healthCheck(): array
    {
        $url = rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/');
        $started = microtime(true);
        $response = Http::timeout(2)->get($url . '/api/v3/ping');

        return [
            'venue' => $this->venueCode(),
            'status' => $response->ok() ? 'HEALTHY' : 'DISCONNECTED',
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    public function getMarkets(): array
    {
        $url = rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/');
        $response = Http::timeout(4)->get($url . '/api/v3/exchangeInfo');

        if (!$response->ok()) {
            return [];
        }

        return (array) ($response->json('symbols') ?? []);
    }

    public function getTicker(string $symbol): array
    {
        $url = rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/');
        $response = Http::timeout(4)->get($url . '/api/v3/ticker/24hr', ['symbol' => strtoupper($symbol)]);

        if (!$response->ok()) {
            return ['venue' => $this->venueCode(), 'status' => 'UNAVAILABLE'];
        }

        return array_merge(['venue' => $this->venueCode(), 'status' => 'HEALTHY'], (array) $response->json());
    }

    public function getOrderBook(string $symbol, int $limit = 20): array
    {
        return $this->liquidityProvider->fetchOrderBook($symbol, $limit);
    }

    public function getBalance(string $asset): array
    {
        return [
            'venue' => $this->venueCode(),
            'asset' => strtoupper($asset),
            'available' => '0',
            'locked' => '0',
            'status' => 'NOT_CONFIGURED',
        ];
    }

    public function placeOrder(array $order): array
    {
        if ((bool) config('services.binance.simulate', true) && app()->environment('production')) {
            throw new RuntimeException('Simulated Binance execution is prohibited in production.');
        }

        return $this->liquidityProvider->placeExternalOrder($order);
    }

    public function cancelOrder(string $symbol, string $externalOrderId): array
    {
        return ['venue' => $this->venueCode(), 'symbol' => strtoupper($symbol), 'external_order_id' => $externalOrderId, 'status' => 'NOT_CONFIGURED'];
    }

    public function getOrder(string $symbol, string $externalOrderId): array
    {
        return ['venue' => $this->venueCode(), 'symbol' => strtoupper($symbol), 'external_order_id' => $externalOrderId, 'status' => 'NOT_CONFIGURED'];
    }

    public function getTrades(string $symbol, string $externalOrderId): array
    {
        return ['venue' => $this->venueCode(), 'symbol' => strtoupper($symbol), 'external_order_id' => $externalOrderId, 'trades' => []];
    }
}
