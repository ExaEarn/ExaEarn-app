<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class MarketMakerFairValueService
{
    public function __construct(private readonly MarketDataService $marketData)
    {
    }

    public function fairValue(string $symbol): array
    {
        $ticker = $this->marketData->ticker($symbol);
        $candidate = $ticker['mid_price'] ?? $ticker['last_trade_price'] ?? $ticker['reference_price'] ?? $ticker['last_price'] ?? null;
        if ($candidate === null || FinancialDecimal::compare((string) $candidate, '0') <= 0) {
            throw new RuntimeException('No trusted fair value is available for market-maker quoting.');
        }

        $updatedAt = isset($ticker['updated_at']) ? strtotime((string) $ticker['updated_at']) : time();
        $ageSeconds = max(0, time() - (int) $updatedAt);

        return [
            'symbol' => $ticker['symbol'] ?? $symbol,
            'fair_value' => FinancialDecimal::normalize((string) $candidate),
            'source' => $ticker['source'] ?? MarketDataService::SOURCE_INTERNAL,
            'market_data_status' => $ticker['market_data_status'] ?? 'UNKNOWN',
            'age_seconds' => $ageSeconds,
            'best_bid' => $ticker['best_bid'] ?? null,
            'best_ask' => $ticker['best_ask'] ?? null,
            'reference_price' => $ticker['reference_price'] ?? null,
        ];
    }
}
