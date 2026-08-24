<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Market;
use App\Models\Order;
use App\Models\SpotMarketDataEvent;
use App\Models\SpotOrderBookSnapshot;
use App\Models\Trade;
use App\Services\Spot\SpotEngineModeResolver;
use App\Services\Spot\SpotRealtimeSequenceService;
use Carbon\CarbonImmutable;
use RuntimeException;

class MarketDataService
{
    public const SOURCE_INTERNAL = 'EXAEARN_INTERNAL';
    public const SOURCE_REFERENCE = 'EXTERNAL_REFERENCE';

    public function __construct(
        private readonly SpotEngineModeResolver $modes,
        private readonly SpotRealtimeSequenceService $realtime,
        private readonly ExternalReferenceMarketDataService $reference,
    ) {
    }

    public function normalizeSymbol(string $symbol): string
    {
        $clean = strtoupper(trim($symbol));
        if (str_contains($clean, '/')) {
            return $clean;
        }
        if (str_contains($clean, '-')) {
            [$base, $quote] = array_pad(explode('-', $clean, 2), 2, 'USDT');
            return trim($base) . '/' . trim($quote);
        }

        foreach (['USDT', 'USDC', 'BTC', 'ETH', 'NGN', 'USD'] as $quote) {
            if (str_ends_with($clean, $quote) && strlen($clean) > strlen($quote)) {
                return substr($clean, 0, -strlen($quote)) . '/' . $quote;
            }
        }

        return $clean;
    }

    public function market(string $symbol): Market
    {
        return Market::query()->where('symbol', $this->normalizeSymbol($symbol))->firstOrFail();
    }

    public function symbols(): array
    {
        return Market::query()
            ->where('status', 'active')
            ->orderBy('symbol')
            ->get()
            ->map(fn (Market $market): array => $this->marketContract($market))
            ->all();
    }

    public function tickers(): array
    {
        return Market::query()
            ->where('status', 'active')
            ->orderBy('symbol')
            ->get()
            ->map(fn (Market $market): array => $this->ticker($market))
            ->all();
    }

    public function ticker(string|Market $market): array
    {
        $market = $market instanceof Market ? $market : $this->market($market);
        $now = now();
        $windowStart = $now->copy()->subDay();
        $trades = Trade::query()
            ->where('market_id', $market->id)
            ->where('executed_at', '>=', $windowStart)
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get();
        $lastTrade = Trade::query()
            ->where('market_id', $market->id)
            ->latest('executed_at')
            ->latest('id')
            ->first();
        $book = $this->orderBook($market, 1);

        $open = $trades->first();
        $lastPrice = $lastTrade ? (string) $lastTrade->price : null;
        $openPrice = $open ? (string) $open->price : $lastPrice;
        $high = null;
        $low = null;
        $baseVolume = '0';
        $quoteVolume = '0';

        foreach ($trades as $trade) {
            $price = (string) $trade->price;
            $high = $high === null || FinancialDecimal::compare($price, $high) > 0 ? $price : $high;
            $low = $low === null || FinancialDecimal::compare($price, $low) < 0 ? $price : $low;
            $baseVolume = FinancialDecimal::add($baseVolume, (string) $trade->amount, 18);
            $quoteVolume = FinancialDecimal::add($quoteVolume, (string) $trade->quote_amount, 18);
        }

        $priceChange = ($lastPrice !== null && $openPrice !== null)
            ? FinancialDecimal::sub($lastPrice, $openPrice, 18)
            : null;
        $priceChangePercent = ($priceChange !== null && $openPrice !== null && FinancialDecimal::compare($openPrice, '0') > 0)
            ? FinancialDecimal::mul(FinancialDecimal::div($priceChange, $openPrice, 18), '100', 18)
            : null;

        $internalTicker = array_merge($this->marketContract($market), [
            'last_price' => $lastPrice,
            'last_trade_price' => $lastPrice,
            'reference_price' => null,
            'best_bid' => $book['bids'][0]['price'] ?? null,
            'best_bid_size' => $book['bids'][0]['quantity'] ?? null,
            'best_ask' => $book['asks'][0]['price'] ?? null,
            'best_ask_size' => $book['asks'][0]['quantity'] ?? null,
            'spread' => isset($book['bids'][0], $book['asks'][0]) ? FinancialDecimal::sub((string) $book['asks'][0]['price'], (string) $book['bids'][0]['price'], 18) : null,
            'mid_price' => isset($book['bids'][0], $book['asks'][0]) ? FinancialDecimal::div(FinancialDecimal::add((string) $book['asks'][0]['price'], (string) $book['bids'][0]['price'], 18), '2', 18) : null,
            'open_price_24h' => $openPrice,
            'price_change' => $priceChange,
            'price_change_percent' => $priceChangePercent,
            'high_24h' => $high,
            'low_24h' => $low,
            'base_volume_24h' => $baseVolume,
            'quote_volume_24h' => $quoteVolume,
            'trade_count_24h' => $trades->count(),
            'open_time' => $windowStart->toISOString(),
            'close_time' => $now->toISOString(),
            'sequence' => $book['sequence'],
            'updated_at' => $lastTrade?->executed_at?->toISOString() ?? (string) ($book['timestamp'] ?? $now->toISOString()),
            'market_data_status' => $this->freshnessStatus($market),
            'source' => self::SOURCE_INTERNAL,
            'source_type' => 'internal',
            'is_internal' => true,
        ]);

        $reference = $this->reference->ticker($market->symbol);
        if ($reference === null) {
            return $internalTicker;
        }

        if ($lastPrice !== null) {
            return array_merge($internalTicker, [
                'reference_price' => $reference['last_price'],
                'reference_updated_at' => $reference['updated_at'],
                'reference_provider' => $reference['reference_provider'],
            ]);
        }

        return array_merge($internalTicker, $reference, [
            'last_trade_price' => null,
            'reference_price' => $reference['last_price'],
            'market_data_status' => $this->freshnessStatus($market),
        ]);
    }

    public function orderBook(string|Market $market, int $limit = 50): array
    {
        $market = $market instanceof Market ? $market : $this->market($market);
        $limit = max(1, min($limit, 100));
        $snapshot = SpotOrderBookSnapshot::query()
            ->where('market_id', $market->id)
            ->latest('last_sequence')
            ->first();

        $bids = $snapshot?->bids ?? $this->levelsFromOpenOrders($market, 'buy');
        $asks = $snapshot?->asks ?? $this->levelsFromOpenOrders($market, 'sell');

        $internalBook = [
            'symbol' => $market->symbol,
            'pair' => $market->symbol,
            'sequence' => $snapshot?->last_sequence ?? $this->latestSequence($market),
            'bids' => array_slice($this->normalizeLevels($bids, 'buy'), 0, $limit),
            'asks' => array_slice($this->normalizeLevels($asks, 'sell'), 0, $limit),
            'timestamp' => ($snapshot?->updated_at ?? now())->toISOString(),
            'source' => self::SOURCE_INTERNAL,
            'is_internal' => true,
        ];

        if ($internalBook['bids'] !== [] || $internalBook['asks'] !== []) {
            return $internalBook;
        }

        return $this->reference->orderBook($market->symbol, $limit) ?? $internalBook;
    }

    public function recentTrades(string|Market $market, int $limit = 100): array
    {
        $market = $market instanceof Market ? $market : $this->market($market);
        $limit = max(1, min($limit, 500));

        $trades = Trade::query()
            ->where('market_id', $market->id)
            ->latest('executed_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Trade $trade): array => $this->publicTrade($trade))
            ->all();

        return $trades !== [] ? $trades : $this->reference->recentTrades($market->symbol, $limit);
    }

    public function candles(string|Market $market, string $interval = '1m', int $limit = 500): array
    {
        $market = $market instanceof Market ? $market : $this->market($market);
        $seconds = $this->intervalSeconds($interval);
        $limit = max(1, min($limit, 2000));
        $end = now()->timestamp;
        $start = $end - ($seconds * $limit);

        $trades = Trade::query()
            ->where('market_id', $market->id)
            ->where('executed_at', '>=', CarbonImmutable::createFromTimestamp($start))
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get();

        if ($trades->isEmpty()) {
            return $this->reference->candles($market->symbol, $interval, $limit);
        }

        $buckets = [];
        foreach ($trades as $trade) {
            $openTime = (int) floor($trade->executed_at->timestamp / $seconds) * $seconds;
            if (!isset($buckets[$openTime])) {
                $buckets[$openTime] = [
                    'open_time' => $openTime,
                    'close_time' => $openTime + $seconds - 1,
                    'open' => (string) $trade->price,
                    'high' => (string) $trade->price,
                    'low' => (string) $trade->price,
                    'close' => (string) $trade->price,
                    'base_volume' => '0',
                    'quote_volume' => '0',
                    'trade_count' => 0,
                    'source' => self::SOURCE_INTERNAL,
                ];
            }

            $bucket = &$buckets[$openTime];
            $bucket['high'] = FinancialDecimal::compare((string) $trade->price, $bucket['high']) > 0 ? (string) $trade->price : $bucket['high'];
            $bucket['low'] = FinancialDecimal::compare((string) $trade->price, $bucket['low']) < 0 ? (string) $trade->price : $bucket['low'];
            $bucket['close'] = (string) $trade->price;
            $bucket['base_volume'] = FinancialDecimal::add($bucket['base_volume'], (string) $trade->amount, 18);
            $bucket['quote_volume'] = FinancialDecimal::add($bucket['quote_volume'], (string) $trade->quote_amount, 18);
            $bucket['trade_count']++;
            unset($bucket);
        }

        return array_slice(array_values($buckets), -$limit);
    }

    public function deltas(string|Market $market, int $afterSequence, int $limit = 500): array
    {
        $market = $market instanceof Market ? $market : $this->market($market);
        return $this->realtime->deltasAfter($market, $afterSequence, max(1, min($limit, 1000)));
    }

    public function health(?string $symbol = null): array
    {
        $markets = $symbol ? collect([$this->market($symbol)]) : Market::query()->where('status', 'active')->orderBy('symbol')->get();

        return $markets->mapWithKeys(function (Market $market): array {
            return [$market->symbol => [
                'symbol' => $market->symbol,
                'engine_mode' => $this->modes->mode($market),
                'market_status' => strtoupper((string) ($market->trading_status ?? 'trading')),
                'market_data_status' => $this->freshnessStatus($market),
                'last_engine_sequence' => $this->latestSequence($market),
                'last_event_time' => SpotMarketDataEvent::query()->where('market_id', $market->id)->latest('occurred_at')->value('occurred_at'),
                'last_trade_time' => Trade::query()->where('market_id', $market->id)->latest('executed_at')->value('executed_at'),
                'last_book_update' => SpotOrderBookSnapshot::query()->where('market_id', $market->id)->latest('updated_at')->value('updated_at'),
            ]];
        })->all();
    }

    public function streamTopicsPayload(array $topics, int $afterSequence = 0): array
    {
        $events = [];
        foreach ($topics as $topic) {
            $parsed = $this->parseTopic((string) $topic);
            if ($parsed === null) {
                throw new RuntimeException('Invalid market stream topic.');
            }

            $market = $this->market($parsed['symbol']);
            $events[$topic] = match ($parsed['type']) {
                'ticker' => $this->ticker($market),
                'book' => ['snapshot' => $this->orderBook($market), 'deltas' => $this->deltas($market, $afterSequence)],
                'trade' => $this->recentTrades($market, 100),
                'kline' => $this->candles($market, $parsed['interval'] ?? '1m', 500),
                default => throw new RuntimeException('Unsupported market stream topic.'),
            };
        }

        return ['op' => 'snapshot', 'topics' => $events, 'timestamp' => now()->toISOString()];
    }

    private function parseTopic(string $topic): ?array
    {
        if (!preg_match('/^market\.([A-Z0-9]+)(?:\/|-)?(USDT|USDC|BTC|ETH|USD|NGN)\.(ticker|book|trade|kline)(?:\.([A-Za-z0-9]+))?$/', strtoupper($topic), $matches)) {
            return null;
        }

        return ['symbol' => "{$matches[1]}/{$matches[2]}", 'type' => strtolower($matches[3]), 'interval' => $matches[4] ?? null];
    }

    private function marketContract(Market $market): array
    {
        return [
            'symbol' => $market->symbol,
            'base_asset' => strtoupper((string) $market->base_currency),
            'quote_asset' => strtoupper((string) $market->quote_currency),
            'market_type' => 'spot',
            'status' => strtoupper((string) ($market->trading_status ?? $market->status ?? 'trading')),
            'engine_mode' => $this->modes->mode($market),
            'source' => self::SOURCE_INTERNAL,
            'is_internal' => true,
        ];
    }

    private function publicTrade(Trade $trade): array
    {
        return [
            'trade_id' => $trade->trade_uuid,
            'trade_uuid' => $trade->trade_uuid,
            'symbol' => $trade->pair,
            'pair' => $trade->pair,
            'price' => (string) $trade->price,
            'quantity' => (string) $trade->amount,
            'amount' => (string) $trade->amount,
            'quote_quantity' => (string) $trade->quote_amount,
            'quote_amount' => (string) $trade->quote_amount,
            'side' => (string) data_get($trade->metadata, 'taker_side', 'unknown'),
            'timestamp' => $trade->executed_at?->toISOString(),
            'executed_at' => $trade->executed_at?->toISOString(),
            'sequence' => $trade->sequence,
            'source' => self::SOURCE_INTERNAL,
        ];
    }

    private function levelsFromOpenOrders(Market $market, string $side): array
    {
        $levels = [];
        $orders = Order::query()
            ->where('market_id', $market->id)
            ->where('side', $side)
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy($side === 'buy' ? 'price' : 'price', $side === 'buy' ? 'desc' : 'asc')
            ->orderBy('sequence')
            ->get();

        foreach ($orders as $order) {
            $price = (string) $order->price;
            $levels[$price] = FinancialDecimal::add($levels[$price] ?? '0', (string) $order->remaining_amount, 18);
        }

        return collect($levels)->map(fn (string $quantity, string $price): array => ['price' => $price, 'quantity' => $quantity])->values()->all();
    }

    private function normalizeLevels(array $levels, string $side): array
    {
        return collect($levels)
            ->map(fn (array $level): array => [
                'price' => (string) ($level['price'] ?? '0'),
                'quantity' => (string) ($level['quantity'] ?? $level['amount'] ?? '0'),
                'amount' => (string) ($level['quantity'] ?? $level['amount'] ?? '0'),
                'side' => $side,
            ])
            ->filter(fn (array $level): bool => FinancialDecimal::compare($level['price'], '0') > 0 && FinancialDecimal::compare($level['quantity'], '0') > 0)
            ->sort(fn (array $a, array $b): int => $side === 'buy'
                ? FinancialDecimal::compare($b['price'], $a['price'])
                : FinancialDecimal::compare($a['price'], $b['price']))
            ->values()
            ->all();
    }

    private function latestSequence(Market $market): int
    {
        return (int) SpotMarketDataEvent::query()->where('market_id', $market->id)->max('sequence');
    }

    private function freshnessStatus(Market $market): string
    {
        $latest = SpotMarketDataEvent::query()->where('market_id', $market->id)->latest('occurred_at')->first();
        if (!$latest) {
            return 'NO_INTERNAL_EVENTS';
        }

        return $latest->occurred_at?->lt(now()->subMinutes(5)) ? 'STALE' : 'LIVE';
    }

    private function intervalSeconds(string $interval): int
    {
        return match (strtolower($interval)) {
            '1m' => 60,
            '3m' => 180,
            '5m' => 300,
            '15m' => 900,
            '30m' => 1800,
            '1h', '60' => 3600,
            '4h', '240' => 14400,
            '1d', 'd' => 86400,
            default => throw new RuntimeException('Unsupported kline interval.'),
        };
    }
}
