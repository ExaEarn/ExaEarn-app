<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CryptoLiquidityService
{
    public function getPrice(string $symbol): string
    {
        $response = Http::timeout(8)
            ->get(rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/') . '/api/v3/ticker/price', [
                'symbol' => strtoupper($symbol),
            ]);

        if (!$response->ok()) {
            throw new RuntimeException('Failed to fetch crypto price from Binance.');
        }

        $price = (string) data_get($response->json(), 'price');
        if ($price === '') {
            throw new RuntimeException('Binance price payload invalid.');
        }

        return $this->applySpread($price);
    }

    public function placeOrder(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/');
        $apiKey = (string) config('services.binance.key', '');

        $response = Http::withHeaders(['X-MBX-APIKEY' => $apiKey])
            ->retry((int) config('swap.execution_retry_count', 3), 200)
            ->timeout(10)
            ->post($baseUrl . '/api/v3/order', $payload);

        if (!$response->ok()) {
            throw new RuntimeException('Binance order placement failed.');
        }

        return $response->json();
    }

    private function applySpread(string $price): string
    {
        $spreadPercent = (string) config('swap.crypto_spread_percent', '0.4');
        $multiplier = $this->div($this->add('100', $spreadPercent), '100');

        return $this->mul($price, $multiplier);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 8) : number_format(((float) $a + (float) $b), 8, '.', '');
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, 8) : number_format(((float) $a * (float) $b), 8, '.', '');
    }

    private function div(string $a, string $b): string
    {
        return function_exists('bcdiv') ? bcdiv($a, $b, 8) : number_format(((float) $a / (float) $b), 8, '.', '');
    }
}
