<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExternalReferenceMarketDataService
{
    private const BINANCE_BASE_URL = 'https://api.binance.com';

    public function ticker(string $symbol): ?array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->retry(1, 100)
                ->get(self::BINANCE_BASE_URL . '/api/v3/ticker/24hr', ['symbol' => $this->providerSymbol($symbol)]);

            if (!$response->ok()) {
                return null;
            }

            $data = $response->json();
            $last = (string) ($data['lastPrice'] ?? '0');
            if (FinancialDecimal::compare($last, '0') <= 0) {
                return null;
            }

            return [
                'last_price' => $last,
                'open_price_24h' => (string) ($data['openPrice'] ?? '0'),
                'price_change' => (string) ($data['priceChange'] ?? '0'),
                'price_change_percent' => (string) ($data['priceChangePercent'] ?? '0'),
                'high_24h' => (string) ($data['highPrice'] ?? '0'),
                'low_24h' => (string) ($data['lowPrice'] ?? '0'),
                'base_volume_24h' => (string) ($data['volume'] ?? '0'),
                'quote_volume_24h' => (string) ($data['quoteVolume'] ?? '0'),
                'trade_count_24h' => (int) ($data['count'] ?? 0),
                'updated_at' => now()->toISOString(),
                'source' => MarketDataService::SOURCE_REFERENCE,
                'source_type' => 'reference',
                'is_internal' => false,
                'reference_provider' => 'BINANCE',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function orderBook(string $symbol, int $limit = 50): ?array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->retry(1, 100)
                ->get(self::BINANCE_BASE_URL . '/api/v3/depth', [
                    'symbol' => $this->providerSymbol($symbol),
                    'limit' => max(5, min($limit, 100)),
                ]);

            if (!$response->ok()) {
                return null;
            }

            $data = $response->json();

            return [
                'symbol' => $symbol,
                'pair' => $symbol,
                'sequence' => 0,
                'bids' => $this->levels($data['bids'] ?? [], 'buy'),
                'asks' => $this->levels($data['asks'] ?? [], 'sell'),
                'timestamp' => now()->toISOString(),
                'source' => MarketDataService::SOURCE_REFERENCE,
                'source_type' => 'reference',
                'is_internal' => false,
                'reference_provider' => 'BINANCE',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function recentTrades(string $symbol, int $limit = 100): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->retry(1, 100)
                ->get(self::BINANCE_BASE_URL . '/api/v3/trades', [
                    'symbol' => $this->providerSymbol($symbol),
                    'limit' => max(1, min($limit, 500)),
                ]);

            if (!$response->ok()) {
                return [];
            }

            return collect($response->json())->map(fn (array $row): array => [
                'trade_id' => 'reference-binance-' . (string) ($row['id'] ?? ''),
                'trade_uuid' => 'reference-binance-' . (string) ($row['id'] ?? ''),
                'symbol' => $symbol,
                'pair' => $symbol,
                'price' => (string) ($row['price'] ?? '0'),
                'quantity' => (string) ($row['qty'] ?? '0'),
                'amount' => (string) ($row['qty'] ?? '0'),
                'quote_quantity' => FinancialDecimal::mul((string) ($row['price'] ?? '0'), (string) ($row['qty'] ?? '0'), 18),
                'quote_amount' => FinancialDecimal::mul((string) ($row['price'] ?? '0'), (string) ($row['qty'] ?? '0'), 18),
                'side' => ((bool) ($row['isBuyerMaker'] ?? false)) ? 'sell' : 'buy',
                'timestamp' => isset($row['time']) ? CarbonImmutable::createFromTimestampMs((int) $row['time'])->toISOString() : now()->toISOString(),
                'executed_at' => isset($row['time']) ? CarbonImmutable::createFromTimestampMs((int) $row['time'])->toISOString() : now()->toISOString(),
                'sequence' => 0,
                'source' => MarketDataService::SOURCE_REFERENCE,
                'source_type' => 'reference',
                'is_internal' => false,
                'reference_provider' => 'BINANCE',
            ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function candles(string $symbol, string $interval = '1m', int $limit = 500): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->retry(1, 100)
                ->get(self::BINANCE_BASE_URL . '/api/v3/klines', [
                    'symbol' => $this->providerSymbol($symbol),
                    'interval' => $interval,
                    'limit' => max(1, min($limit, 1000)),
                ]);

            if (!$response->ok()) {
                return [];
            }

            return collect($response->json())->map(fn (array $row): array => [
                'open_time' => (int) floor(((int) ($row[0] ?? 0)) / 1000),
                'close_time' => (int) floor(((int) ($row[6] ?? 0)) / 1000),
                'open' => (string) ($row[1] ?? '0'),
                'high' => (string) ($row[2] ?? '0'),
                'low' => (string) ($row[3] ?? '0'),
                'close' => (string) ($row[4] ?? '0'),
                'base_volume' => (string) ($row[5] ?? '0'),
                'quote_volume' => (string) ($row[7] ?? '0'),
                'trade_count' => (int) ($row[8] ?? 0),
                'source' => MarketDataService::SOURCE_REFERENCE,
                'source_type' => 'reference',
                'is_internal' => false,
                'reference_provider' => 'BINANCE',
            ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function levels(array $rows, string $side): array
    {
        return collect($rows)->map(fn (array $row): array => [
            'price' => (string) ($row[0] ?? '0'),
            'quantity' => (string) ($row[1] ?? '0'),
            'amount' => (string) ($row[1] ?? '0'),
            'side' => $side,
        ])->all();
    }

    private function providerSymbol(string $symbol): string
    {
        return strtoupper(str_replace(['/', '-'], '', $symbol));
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('trading.market_data.provider_timeout_seconds', 4));
    }
}
