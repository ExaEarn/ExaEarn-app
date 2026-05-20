<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketData;

class MarketDataCollectorService
{
    public function collect(array $payload): MarketData
    {
        return MarketData::query()->create([
            'symbol' => strtoupper((string) ($payload['symbol'] ?? 'BTCUSDT')),
            'price' => (string) ($payload['price'] ?? '0'),
            'volume' => (string) ($payload['volume'] ?? '0'),
            'spread' => (string) ($payload['spread'] ?? '0'),
            'volatility' => isset($payload['volatility']) ? (string) $payload['volatility'] : null,
            'timestamp' => $payload['timestamp'] ?? now(),
        ]);
    }

    public function recent(string $symbol, int $limit = 120)
    {
        return MarketData::query()
            ->where('symbol', strtoupper($symbol))
            ->latest('timestamp')
            ->limit($limit)
            ->get();
    }
}
