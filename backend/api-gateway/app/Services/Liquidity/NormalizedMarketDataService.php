<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\Market;
use App\Services\FinancialDecimal;
use App\Services\MarketDataService;

class NormalizedMarketDataService
{
    public function __construct(
        private readonly MarketDataService $internal,
        private readonly LiquiditySourceRegistry $sources,
    ) {
    }

    public function internalBook(string $symbol, int $limit = 20): array
    {
        $book = $this->internal->orderBook(strtoupper($symbol), $limit);
        if (($book['source'] ?? null) !== MarketDataService::SOURCE_INTERNAL || ($book['is_internal'] ?? false) !== true) {
            $book['bids'] = [];
            $book['asks'] = [];
        }

        return [
            'source' => 'EXAEARN_INTERNAL',
            'source_type' => 'INTERNAL_ORDER_BOOK',
            'venue' => 'EXAEARN',
            'symbol' => strtoupper($symbol),
            'bids' => $this->normalizeLevels($book['bids'] ?? []),
            'asks' => $this->normalizeLevels($book['asks'] ?? []),
            'timestamp' => (string) ($book['timestamp'] ?? now()->toISOString()),
            'executable' => true,
            'fees' => ['maker_bps' => '0', 'taker_bps' => '0'],
            'latency_ms' => 0,
        ];
    }

    public function externalBook(string $source, string $symbol, int $limit = 20): array
    {
        $book = $this->sources->adapter($source)->getOrderBook($symbol, $limit);

        return [
            'source' => strtoupper($source),
            'source_type' => (string) ($book['source_type'] ?? 'EXTERNAL_REFERENCE'),
            'venue' => strtoupper($source),
            'symbol' => strtoupper($symbol),
            'bids' => $this->normalizeLevels($book['bids'] ?? []),
            'asks' => $this->normalizeLevels($book['asks'] ?? []),
            'timestamp' => (string) ($book['timestamp'] ?? now()->toISOString()),
            'executable' => (bool) ($book['executable'] ?? false),
            'fees' => $this->sources->adapter($source)->getTradingFees($symbol),
            'latency_ms' => (int) ($book['latency_ms'] ?? 0),
        ];
    }

    public function normalizeMarket(Market $market): array
    {
        return [
            'symbol' => strtoupper((string) $market->symbol),
            'base_asset' => strtoupper((string) $market->base_currency),
            'quote_asset' => strtoupper((string) $market->quote_currency),
            'status' => (string) $market->status,
            'price_precision' => (int) ($market->price_precision ?? 8),
            'quantity_precision' => (int) ($market->quantity_precision ?? 8),
        ];
    }

    private function normalizeLevels(array $levels): array
    {
        $normalized = [];
        foreach ($levels as $level) {
            $price = FinancialDecimal::normalize((string) ($level['price'] ?? $level[0] ?? '0'));
            $quantity = FinancialDecimal::normalize((string) ($level['quantity'] ?? $level['amount'] ?? $level[1] ?? '0'));
            if (FinancialDecimal::compare($price, '0') <= 0 || FinancialDecimal::compare($quantity, '0') <= 0) {
                continue;
            }
            $normalized[] = ['price' => $price, 'quantity' => $quantity];
        }

        return $normalized;
    }
}
