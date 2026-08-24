<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\CalculateRewardJob;
use App\Models\AuditLog;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderBook;
use App\Models\Trade;
use App\Models\Transaction;
use App\Services\Spot\OrderManagementService;
use App\Services\Spot\SpotEngineModeResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TradeService
{
    private const SCALE = 8;
    private const DEFAULT_LIVE_MARKET_SYMBOLS = [
        'BTCUSDT',
        'ETHUSDT',
        'SOLUSDT',
        'XRPUSDT',
        'BNBUSDT',
        'ADAUSDT',
        'DOTUSDT',
        'AVAXUSDT',
        'MATICUSDT',
        'ATOMUSDT',
        'LINKUSDT',
    ];

    public function __construct(
        private readonly MarketStreamService $marketStream,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlements,
        private readonly ReferralService $referrals,
        private readonly OrderManagementService $orderManagement,
        private readonly SpotEngineModeResolver $engineModes,
    ) {}

    public function listMarkets(): Collection
    {
        $cached = Cache::get('trade:markets:snapshot');
        if ($cached !== null) {
            return collect($cached);
        }

        $markets = Market::query()->orderBy('symbol')->get();
        $dbSymbols = $markets
            ->pluck('symbol')
            ->map(fn (string $symbol): string => $this->toExternalSymbol($symbol))
            ->values()
            ->all();

        $symbols = collect(array_merge(self::DEFAULT_LIVE_MARKET_SYMBOLS, $dbSymbols))
            ->filter(fn (string $symbol): bool => $symbol !== '')
            ->unique()
            ->values()
            ->all();

        $liveTickers = app()->environment('local') && (bool) config('services.market_data.skip_external_on_local_request', true)
            ? []
            : $this->fetchLiveTickers($symbols);

        $payloads = $markets->mapWithKeys(function (Market $market) use ($liveTickers): array {
            $symbol = $this->toExternalSymbol($market->symbol);
            $ticker = $liveTickers[$symbol] ?? [];

            if (isset($ticker['lastPrice']) && (float) $ticker['lastPrice'] > 0) {
                $market->forceFill(['last_price' => (string) $ticker['lastPrice']])->save();
            }

            $payload = $this->marketPayload($market->toArray(), $ticker);

            return [$payload['symbol'] => $payload];
        });

        foreach ($symbols as $symbol) {
            $pair = $this->pairFromExternalSymbol($symbol);
            if ($payloads->has($pair)) {
                continue;
            }

            [$base, $quote] = $this->splitPair($pair);
            $payloads->put($pair, $this->marketPayload([
                'symbol' => $pair,
                'base_currency' => $base,
                'quote_currency' => $quote,
                'status' => 'active',
                'last_price' => (string) (($liveTickers[$symbol]['lastPrice'] ?? '0')),
                'price_precision' => '0.00010000',
                'min_order_size' => '0.00010000',
                'max_order_size' => '0.00000000',
                'maker_fee' => '0.00100000',
                'taker_fee' => '0.00200000',
            ], $liveTickers[$symbol] ?? []));
        }

        $result = $payloads->sortBy('symbol')->values();
        Cache::put('trade:markets:snapshot', $result->all(), now()->addSeconds((int) config('services.market_data.snapshot_cache_seconds', 10)));

        return $result;
    }
    private function marketProviderTimeout(): float
    {
        return max(0.25, (float) config('services.market_data.timeout_seconds', 0.75));
    }

    private function marketProviderRetries(): int
    {
        return max(0, (int) config('services.market_data.retries', 0));
    }
    /**
     * @param  array<int, string>  $symbols
     * @return array<string, array<string, mixed>>
     */
    private function fetchLiveTickers(array $symbols): array
    {
        $binance = $this->fetchBinanceTickers($symbols);
        if ($binance !== []) {
            return $binance;
        }

        return $this->fetchCoinGeckoTickers($symbols);
    }


    /**
     * @param  array<int, string>  $symbols
     * @return array<string, array<string, mixed>>
     */
    private function fetchBinanceTickers(array $symbols): array
    {
        try {
            $response = Http::timeout($this->marketProviderTimeout())
                ->connectTimeout(min(0.5, $this->marketProviderTimeout()))
                ->retry($this->marketProviderRetries(), 100)
                ->get(rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/').'/api/v3/ticker/24hr', [
                    'symbols' => json_encode(array_values(array_unique($symbols)), JSON_THROW_ON_ERROR),
                ]);

            if (! $response->ok()) {
                return [];
            }

            return collect($response->json())
                ->filter(fn ($item): bool => is_array($item) && isset($item['symbol']))
                ->map(fn (array $item): array => array_merge($item, ['source' => 'binance']))
                ->keyBy(fn (array $item): string => strtoupper((string) $item['symbol']))
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{bids: array<int, array<string, mixed>>, asks: array<int, array<string, mixed>>, last_synced_at: string}|array{}
     */
    private function fetchBinanceDepth(string $symbol, int $depth): array
    {
        try {
            $response = Http::timeout($this->marketProviderTimeout())
                ->connectTimeout(min(0.5, $this->marketProviderTimeout()))
                ->retry($this->marketProviderRetries(), 100)
                ->get(rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/').'/api/v3/depth', [
                    'symbol' => $symbol,
                    'limit' => max(5, min($depth, 100)),
                ]);

            if (! $response->ok()) {
                return [];
            }

            $payload = $response->json();

            return [
                'bids' => collect($payload['bids'] ?? [])->map(fn (array $row): array => [
                    'price' => (string) ($row[0] ?? '0'),
                    'amount' => (string) ($row[1] ?? '0'),
                    'side' => 'buy',
                ])->values()->all(),
                'asks' => collect($payload['asks'] ?? [])->map(fn (array $row): array => [
                    'price' => (string) ($row[0] ?? '0'),
                    'amount' => (string) ($row[1] ?? '0'),
                    'side' => 'sell',
                ])->values()->all(),
                'last_synced_at' => now()->toISOString(),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBinanceRecentTrades(string $symbol, string $pair, int $limit): array
    {
        try {
            $response = Http::timeout($this->marketProviderTimeout())
                ->connectTimeout(min(0.5, $this->marketProviderTimeout()))
                ->retry($this->marketProviderRetries(), 100)
                ->get(rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/').'/api/v3/trades', [
                    'symbol' => $symbol,
                    'limit' => max(1, min($limit, 1000)),
                ]);

            if (! $response->ok()) {
                return [];
            }

            return collect($response->json())
                ->filter(fn ($item): bool => is_array($item) && isset($item['price'], $item['qty']))
                ->map(function (array $item) use ($pair): array {
                    $timestamp = (int) ($item['time'] ?? 0);

                    return [
                        'trade_uuid' => 'binance-'.(string) ($item['id'] ?? Str::uuid()),
                        'pair' => $pair,
                        'price' => (string) $item['price'],
                        'amount' => (string) $item['qty'],
                        'quote_amount' => bcmul((string) $item['price'], (string) $item['qty'], self::SCALE),
                        'executed_at' => $timestamp > 0 ? gmdate('c', (int) floor($timestamp / 1000)) : now()->toISOString(),
                        'side' => (($item['isBuyerMaker'] ?? false) === true) ? 'sell' : 'buy',
                        'metadata' => [
                            'source' => 'binance',
                            'is_buyer_maker' => (bool) ($item['isBuyerMaker'] ?? false),
                        ],
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function fetchBinanceCandles(string $symbol, string $timeframe, int $limit): array
    {
        try {
            $response = Http::timeout($this->marketProviderTimeout())
                ->connectTimeout(min(0.5, $this->marketProviderTimeout()))
                ->retry($this->marketProviderRetries(), 100)
                ->get(rtrim((string) config('services.binance.url', 'https://api.binance.com'), '/').'/api/v3/klines', [
                    'symbol' => $symbol,
                    'interval' => $timeframe,
                    'limit' => max(1, min($limit, 1000)),
                ]);

            if (! $response->ok()) {
                return [];
            }

            return collect($response->json())
                ->filter(fn ($row): bool => is_array($row) && isset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5]))
                ->map(fn (array $row): array => [
                    'timestamp' => (int) floor(((int) $row[0]) / 1000),
                    'open' => (string) $row[1],
                    'high' => (string) $row[2],
                    'low' => (string) $row[3],
                    'close' => (string) $row[4],
                    'volume' => (string) $row[5],
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }


    /**
     * @param  array<int, string>  $symbols
     * @return array<string, array<string, mixed>>
     */
    private function fetchCoinGeckoTickers(array $symbols): array
    {
        $coinIds = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'XRP' => 'ripple',
            'SOL' => 'solana',
            'BNB' => 'binancecoin',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'MATIC' => 'matic-network',
            'AVAX' => 'avalanche-2',
            'DOT' => 'polkadot',
            'ATOM' => 'cosmos',
            'LINK' => 'chainlink',
        ];

        $bases = collect($symbols)
            ->map(fn (string $symbol): string => str_ends_with($symbol, 'USDT') ? substr($symbol, 0, -4) : $symbol)
            ->filter(fn (string $base): bool => isset($coinIds[$base]))
            ->unique()
            ->values();

        if ($bases->isEmpty()) {
            return [];
        }

        try {
            $ids = $bases->map(fn (string $base): string => $coinIds[$base])->implode(',');
            $response = Http::timeout($this->marketProviderTimeout())
                ->connectTimeout(min(0.5, $this->marketProviderTimeout()))
                ->retry($this->marketProviderRetries(), 100)
                ->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => $ids,
                    'vs_currencies' => 'usd',
                    'include_24hr_change' => 'true',
                    'include_24hr_vol' => 'true',
                ]);

            if (! $response->ok()) {
                return [];
            }

            $json = $response->json();

            return $bases->mapWithKeys(function (string $base) use ($coinIds, $json): array {
                $coin = $json[$coinIds[$base]] ?? [];

                return [
                    "{$base}USDT" => [
                        'symbol' => "{$base}USDT",
                        'lastPrice' => (string) ($coin['usd'] ?? '0'),
                        'priceChangePercent' => (string) ($coin['usd_24h_change'] ?? '0'),
                        'quoteVolume' => (string) ($coin['usd_24h_vol'] ?? '0'),
                        'source' => 'coingecko',
                    ],
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $market
     * @param  array<string, mixed>  $ticker
     * @return array<string, mixed>
     */
    private function marketPayload(array $market, array $ticker = []): array
    {
        $base = strtoupper((string) ($market['base_currency'] ?? ''));
        $quote = strtoupper((string) ($market['quote_currency'] ?? 'USDT'));
        $symbol = strtoupper((string) ($market['symbol'] ?? "{$base}/{$quote}"));
        $pair = str_contains($symbol, '/') ? $symbol : "{$base}/{$quote}";
        $last = (string) ($ticker['lastPrice'] ?? $market['last_price'] ?? '0');

        return array_merge($market, [
            'symbol' => $symbol,
            'pair' => $pair,
            'base' => $base,
            'quote' => $quote,
            'last' => (float) $last,
            'last_price' => $last,
            'change24h' => (float) ($ticker['priceChangePercent'] ?? 0),
            'price_change_percent' => (float) ($ticker['priceChangePercent'] ?? 0),
            'volume' => (float) ($ticker['quoteVolume'] ?? 0),
            'high24h' => (float) ($ticker['highPrice'] ?? 0),
            'low24h' => (float) ($ticker['lowPrice'] ?? 0),
            'source' => $ticker['source'] ?? ($ticker === [] ? 'database' : 'live'),
            'synced_at' => now()->toISOString(),
        ]);
    }

    public function getOrderBook(string $pair, int $depth = 50): array
    {
        $market = $this->getMarket($pair);
        $snapshot = OrderBook::query()->firstOrCreate(
            ['market_id' => $market->id, 'pair' => $market->symbol],
            ['bid_orders' => [], 'ask_orders' => [], 'last_synced_at' => now()]
        );

        $bids = array_slice($snapshot->bid_orders ?? [], 0, $depth);
        $asks = array_slice($snapshot->ask_orders ?? [], 0, $depth);

        if ($bids === [] && $asks === []) {
            $external = $this->fetchBinanceDepth($this->toExternalSymbol($market->symbol), $depth);
            if ($external !== []) {
                return array_merge(['pair' => $market->symbol], $external);
            }
        }

        return [
            'pair' => $market->symbol,
            'bids' => $bids,
            'asks' => $asks,
            'last_synced_at' => $snapshot->last_synced_at,
        ];
    }

    public function getRecentTrades(string $pair, int $limit = 100): Collection
    {
        $market = $this->getMarket($pair);

        $trades = Trade::query()
            ->where('market_id', $market->id)
            ->latest('executed_at')
            ->limit($limit)
            ->get();

        if ($trades->isNotEmpty()) {
            return $trades;
        }

        return collect($this->fetchBinanceRecentTrades($this->toExternalSymbol($market->symbol), $market->symbol, $limit));
    }

    public function getCandles(string $pair, string $timeframe = '1m', int $limit = 100): array
    {
        $market = $this->getMarket($pair);
        $seconds = match ($timeframe) {
            '1m' => 60,
            '3m' => 180,
            '5m' => 300,
            '15m' => 900,
            '30m' => 1800,
            '1h' => 3600,
            '2h' => 7200,
            '4h' => 14400,
            '6h' => 21600,
            '12h' => 43200,
            '1d' => 86400,
            '1w' => 604800,
            '1M' => 2592000,
            default => throw new RuntimeException('Unsupported timeframe.'),
        };

        $trades = Trade::query()
            ->where('market_id', $market->id)
            ->orderBy('executed_at')
            ->get();

        if ($trades->isEmpty()) {
            $externalCandles = $this->fetchBinanceCandles($this->toExternalSymbol($market->symbol), $timeframe, $limit);
            if ($externalCandles !== []) {
                return $externalCandles;
            }
        }

        $buckets = [];
        foreach ($trades as $trade) {
            $bucket = (int) floor($trade->executed_at->timestamp / $seconds) * $seconds;
            if (! isset($buckets[$bucket])) {
                $buckets[$bucket] = [
                    'timestamp' => $bucket,
                    'open' => (string) $trade->price,
                    'high' => (string) $trade->price,
                    'low' => (string) $trade->price,
                    'close' => (string) $trade->price,
                    'volume' => '0',
                ];
            }

            $buckets[$bucket]['high'] = $this->compare((string) $trade->price, $buckets[$bucket]['high']) > 0 ? (string) $trade->price : $buckets[$bucket]['high'];
            $buckets[$bucket]['low'] = $this->compare((string) $trade->price, $buckets[$bucket]['low']) < 0 ? (string) $trade->price : $buckets[$bucket]['low'];
            $buckets[$bucket]['close'] = (string) $trade->price;
            $buckets[$bucket]['volume'] = $this->add($buckets[$bucket]['volume'], (string) $trade->amount);
        }

        return array_slice(array_values($buckets), -$limit);
    }

    private function toExternalSymbol(string $pair): string
    {
        return strtoupper(str_replace('/', '', str_replace('-', '/', trim($pair))));
    }

    private function pairFromExternalSymbol(string $symbol): string
    {
        $clean = strtoupper(trim($symbol));
        foreach (['USDT', 'USDC', 'BTC', 'ETH'] as $quote) {
            if (str_ends_with($clean, $quote) && strlen($clean) > strlen($quote)) {
                return sprintf('%s/%s', substr($clean, 0, -strlen($quote)), $quote);
            }
        }

        return $clean;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPair(string $pair): array
    {
        return array_pad(explode('/', strtoupper($pair), 2), 2, 'USDT');
    }

    public function placeOrder(
        int $userId,
        string $pair,
        string $side,
        string $type,
        string $amount,
        ?string $price = null,
        array $metadata = []
    ): array {
        $market = $this->getMarket($pair);
        $this->engineModes->assertOrderEntryAllowed($market);
        app(TradingRiskEngine::class)->assertOrderAllowed($userId, 'spot', $market, [
            'pair' => $market->symbol,
            'side' => $side,
            'type' => $type,
            'amount' => $amount,
            'price' => $price,
        ]);
        if ($this->engineModes->isNewAuthority($market) && !in_array(strtolower($type), ['stop_loss', 'take_profit'], true)) {
            $result = $this->orderManagement->placeOrder($userId, [
                'pair' => $market->symbol,
                'side' => $side,
                'type' => $type,
                'amount' => $amount,
                'price' => $price,
                'time_in_force' => $metadata['time_in_force'] ?? 'GTC',
                'post_only' => $metadata['post_only'] ?? false,
                'client_order_id' => $metadata['client_order_id'] ?? null,
                'account_type' => $metadata['account_type'] ?? 'unified_trading',
                'metadata' => $metadata,
            ]);

            if (($result['trades'] ?? []) !== []) {
                $this->referrals->queueQualifiedActivity($userId, 'first_trade', [
                    'event_key' => $result['order']->order_uuid,
                    'transaction_id' => $result['order']->order_uuid,
                    'ip_address' => request()?->ip(),
                ]);
            }

            return $result;
        }

        if (!$this->engineModes->isLegacyAuthority($market)) {
            throw new RuntimeException('No authoritative Spot engine is available for this market.');
        }

        $side = strtolower($side);
        $type = strtolower($type);

        if (! in_array($side, ['buy', 'sell'], true)) {
            throw new RuntimeException('Invalid order side.');
        }

        if (! in_array($type, ['market', 'limit', 'stop_loss', 'take_profit'], true)) {
            throw new RuntimeException('Invalid order type.');
        }

        if ($market->status !== 'active') {
            throw new RuntimeException('Market is not active.');
        }

        if ($this->compare($amount, (string) $market->min_order_size) < 0) {
            throw new RuntimeException('Order amount below market minimum.');
        }

        if ($this->compare((string) $market->max_order_size, '0') > 0 && $this->compare($amount, (string) $market->max_order_size) > 0) {
            throw new RuntimeException('Order amount exceeds market maximum.');
        }

        $isConditional = in_array($type, ['stop_loss', 'take_profit'], true);
        $stopPrice = isset($metadata['stop_price']) && $metadata['stop_price'] !== null ? (string) $metadata['stop_price'] : null;
        $triggerOrderType = strtolower((string) ($metadata['trigger_order_type'] ?? 'market'));

        if ($isConditional && ($stopPrice === null || $this->compare($stopPrice, '0') <= 0)) {
            throw new RuntimeException('Conditional orders require a valid stop_price.');
        }

        if ($isConditional && ! in_array($triggerOrderType, ['market', 'limit'], true)) {
            throw new RuntimeException('Conditional orders require a valid trigger order type.');
        }

        $executionType = $isConditional ? $triggerOrderType : $type;
        $matchPrice = $price;
        if ($executionType === 'market' && ! $isConditional) {
            $bestCounterOrder = $this->getBestCounterOrder($market->id, $side);
            if (! $bestCounterOrder) {
                throw new RuntimeException('No liquidity available for market order.');
            }
            $matchPrice = (string) $bestCounterOrder->price;
        }

        if ($executionType === 'limit' && ($matchPrice === null || $this->compare($matchPrice, '0') <= 0)) {
            throw new RuntimeException('Price is required.');
        }

        $orderUuid = (string) Str::uuid();
        $lockCurrency = $side === 'buy' ? $market->quote_currency : $market->base_currency;
        $lockAmount = $side === 'buy'
            ? ($executionType === 'market'
                ? ($isConditional ? $this->mul($amount, $stopPrice) : $this->quoteRequiredForBuyAmount($market->id, $amount))
                : $this->mul($amount, (string) $matchPrice))
            : $amount;

        if ($side === 'buy' && $executionType === 'market') {
            $slippageBps = FinancialDecimal::normalize((string) config('trading.market_order_slippage_bps', '100'));
            $lockAmount = $this->add($lockAmount, $this->div($this->mul($lockAmount, $slippageBps), '10000'));
            $maximumSpend = isset($metadata['maximum_spend']) ? FinancialDecimal::normalize((string) $metadata['maximum_spend']) : null;
            if ($maximumSpend !== null && $this->compare($lockAmount, $maximumSpend) > 0) {
                throw new RuntimeException('Market order reservation exceeds maximum spend.');
            }
        }

        $order = DB::transaction(function () use ($amount, $executionType, $isConditional, $lockAmount, $lockCurrency, $market, $matchPrice, $metadata, $orderUuid, $side, $stopPrice, $triggerOrderType, $type, $userId): Order {
            $reservation = $this->reservations->reserveUserAccount(
                $userId, 'unified_trading', (string) $lockCurrency, $lockAmount, 'spot_order',
                'order', $orderUuid, "spot-order:{$orderUuid}",
                array_merge($metadata, ['product' => 'spot', 'market' => $market->symbol, 'side' => $side, 'order_type' => $type])
            );

            return Order::create([
            'order_uuid' => $orderUuid,
            'user_id' => $userId,
            'market_id' => $market->id,
            'pair' => $market->symbol,
            'side' => $side,
            'type' => $type,
            'trigger_order_type' => $isConditional ? $triggerOrderType : null,
            'price' => $executionType === 'limit' ? $matchPrice : 0,
            'stop_price' => $stopPrice,
            'amount' => $amount,
            'filled_amount' => '0',
            'remaining_amount' => $amount,
            'locked_amount' => $lockAmount,
            'locked_currency' => $lockCurrency,
            'status' => $isConditional ? 'pending_trigger' : 'open',
            'metadata' => array_merge($metadata, ['reservation_id' => $reservation->reservation_id]),
            ]);
        });

        $trades = $isConditional ? [] : $this->matchOrder($order);
        $this->refreshOrderBook($market);
        if (! $isConditional) {
            $this->processConditionalOrders($market);
        }

        $result = [
            'order' => $order->fresh(),
            'trades' => $trades,
            'order_book' => $this->getOrderBook($market->symbol),
        ];

        if (! $isConditional && $trades !== []) {
            $this->referrals->queueQualifiedActivity($userId, 'first_trade', [
                'event_key' => $order->order_uuid,
                'transaction_id' => $order->order_uuid,
                'ip_address' => request()?->ip(),
            ]);

            CalculateRewardJob::dispatch($userId, 'trade_volume', $amount, [
                'activity_key' => $order->order_uuid,
                'transaction_id' => $order->order_uuid,
                'ip_address' => request()?->ip(),
            ])->onQueue('rewards');
        }

        return $result;
    }

    public function swap(
        int $userId,
        string $fromCurrency,
        string $toCurrency,
        string $amount,
        array $metadata = []
    ): array {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            throw new RuntimeException('Swap assets must be different.');
        }

        $directPair = "{$fromCurrency}/{$toCurrency}";
        $directMarket = Market::query()->where('symbol', $directPair)->first();
        if ($directMarket && $directMarket->status === 'active') {
            $result = $this->placeOrder(
                $userId,
                $directPair,
                'sell',
                'market',
                $amount,
                null,
                array_merge($metadata, [
                    'source' => 'swap',
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                ])
            );

            return [
                'market' => $directPair,
                'direction' => 'sell',
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'spent_amount' => $amount,
                'received_amount' => $this->sumTradeMetadata($result['trades'], 'seller_net_quote'),
                'result' => $result,
            ];
        }

        $inversePair = "{$toCurrency}/{$fromCurrency}";
        $inverseMarket = Market::query()->where('symbol', $inversePair)->first();
        if ($inverseMarket && $inverseMarket->status === 'active') {
            $baseAmount = $this->buyAmountFromQuoteBudget($inverseMarket->id, $amount);
            if ($this->compare($baseAmount, '0') <= 0) {
                throw new RuntimeException('No liquidity available for swap.');
            }

            $result = $this->placeOrder(
                $userId,
                $inversePair,
                'buy',
                'market',
                $baseAmount,
                null,
                array_merge($metadata, [
                    'source' => 'swap',
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'budget_amount' => $amount,
                ])
            );

            return [
                'market' => $inversePair,
                'direction' => 'buy',
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'spent_amount' => $this->sumTradeField($result['trades'], 'quote_amount'),
                'received_amount' => $this->sumTradeMetadata($result['trades'], 'buyer_net_base'),
                'result' => $result,
            ];
        }

        throw new RuntimeException('No supported market exists for this swap.');
    }

    public function cancelOrder(int $userId, string $orderUuid): Order
    {
        $existing = Order::query()->where('order_uuid', $orderUuid)->where('user_id', $userId)->with('market')->firstOrFail();
        if ($this->engineModes->isNewAuthority($existing->market)) {
            return $this->orderManagement->cancelOrder($userId, $orderUuid);
        }

        if (!$this->engineModes->isLegacyAuthority($existing->market)) {
            throw new RuntimeException('Market is not accepting order cancellations through this engine mode.');
        }

        $order = DB::transaction(function () use ($orderUuid, $userId): Order {
            $order = Order::query()->where('order_uuid', $orderUuid)->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') {
                return $order;
            }
            if (! in_array($order->status, ['open', 'partially_filled', 'pending_trigger'], true)) {
                throw new RuntimeException('Only open orders can be cancelled.');
            }
            $reservationId = (string) data_get($order->metadata, 'reservation_id', '');
            if ($reservationId !== '' && $this->compare((string) $order->locked_amount, '0') > 0) {
                $this->reservations->release($reservationId, null, ['event' => 'order_cancel', 'order_uuid' => $order->order_uuid]);
            }
            $order->status = 'cancelled';
            $order->locked_amount = '0';
            $order->save();
            return $order;
        });

        $this->refreshOrderBook($order->market);

        return $order->fresh();
    }

    private function matchOrder(Order $incomingOrder): array
    {
        $trades = [];
        $market = $incomingOrder->market;
        if (!$this->engineModes->isLegacyAuthority($market)) {
            throw new RuntimeException('Legacy matcher cannot execute for this market mode.');
        }
        $isBuy = $incomingOrder->side === 'buy';

        $query = Order::query()
            ->where('market_id', $incomingOrder->market_id)
            ->where('side', $isBuy ? 'sell' : 'buy')
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('user_id', '!=', $incomingOrder->user_id)
            ->where('id', '!=', $incomingOrder->id);

        if ($isBuy) {
            $query->when($incomingOrder->type === 'limit', fn ($builder) => $builder->where('price', '<=', $incomingOrder->price))
                ->orderBy('price')
                ->orderBy('created_at');
        } else {
            $query->when($incomingOrder->type === 'limit', fn ($builder) => $builder->where('price', '>=', $incomingOrder->price))
                ->orderByDesc('price')
                ->orderBy('created_at');
        }

        $counterOrders = $query->lockForUpdate()->get();

        foreach ($counterOrders as $counterOrder) {
            if ($this->compare((string) $incomingOrder->remaining_amount, '0') <= 0) {
                break;
            }

            $tradeAmount = $this->min((string) $incomingOrder->remaining_amount, (string) $counterOrder->remaining_amount);
            $executionPrice = (string) $counterOrder->price;
            $quoteAmount = $this->mul($tradeAmount, $executionPrice);

            DB::transaction(function () use ($incomingOrder, $counterOrder, $tradeAmount, $executionPrice, $quoteAmount, $market, &$trades): void {
                $incomingLocked = Order::query()->lockForUpdate()->findOrFail($incomingOrder->id);
                $counterLocked = Order::query()->lockForUpdate()->findOrFail($counterOrder->id);
                $buyOrder = $incomingLocked->side === 'buy' ? $incomingLocked : $counterLocked;
                $sellOrder = $incomingLocked->side === 'sell' ? $incomingLocked : $counterLocked;
                $makerOrder = $counterLocked;
                $takerOrder = $incomingLocked;

                $buyReserve = $buyOrder->type === 'market' ? $quoteAmount : $this->mul($tradeAmount, (string) $buyOrder->price);
                $refund = $buyOrder->type === 'market' ? '0' : $this->sub($buyReserve, $quoteAmount);
                $makerFeeAmount = $makerOrder->side === 'buy'
                    ? $this->mul($tradeAmount, (string) config('trading.maker_fee', '0.001'))
                    : $this->mul($quoteAmount, (string) config('trading.maker_fee', '0.001'));
                $takerFeeAmount = $takerOrder->side === 'buy'
                    ? $this->mul($tradeAmount, (string) config('trading.taker_fee', '0.002'))
                    : $this->mul($quoteAmount, (string) config('trading.taker_fee', '0.002'));
                $buyFee = $buyOrder->id === $makerOrder->id ? $makerFeeAmount : $takerFeeAmount;
                $sellFee = $sellOrder->id === $makerOrder->id ? $makerFeeAmount : $takerFeeAmount;
                $buyNetBase = $this->sub($tradeAmount, $buyFee);
                $sellNetQuote = $this->sub($quoteAmount, $sellFee);

                $buyReservationId = (string) data_get($buyOrder->metadata, 'reservation_id', '');
                $sellReservationId = (string) data_get($sellOrder->metadata, 'reservation_id', '');
                if ($buyReservationId === '' || $sellReservationId === '') {
                    throw new RuntimeException('Spot order is missing its canonical reservation.');
                }

                $buyOrder->filled_amount = $this->add((string) $buyOrder->filled_amount, $tradeAmount);
                $buyOrder->remaining_amount = $this->sub((string) $buyOrder->remaining_amount, $tradeAmount);
                $buyOrder->locked_amount = $this->sub((string) $buyOrder->locked_amount, $buyReserve);
                $buyOrder->status = $this->compare((string) $buyOrder->remaining_amount, '0') <= 0 ? 'filled' : 'partially_filled';
                $buyOrder->save();

                $sellOrder->filled_amount = $this->add((string) $sellOrder->filled_amount, $tradeAmount);
                $sellOrder->remaining_amount = $this->sub((string) $sellOrder->remaining_amount, $tradeAmount);
                $sellOrder->locked_amount = $this->sub((string) $sellOrder->locked_amount, $tradeAmount);
                $sellOrder->status = $this->compare((string) $sellOrder->remaining_amount, '0') <= 0 ? 'filled' : 'partially_filled';
                $sellOrder->save();

                $trade = Trade::create([
                    'trade_uuid' => (string) Str::uuid(),
                    'market_id' => $market->id,
                    'buy_order_id' => $buyOrder->id,
                    'sell_order_id' => $sellOrder->id,
                    'pair' => $market->symbol,
                    'price' => $executionPrice,
                    'amount' => $tradeAmount,
                    'quote_amount' => $quoteAmount,
                    'maker_fee' => $makerFeeAmount,
                    'taker_fee' => $takerFeeAmount,
                    'executed_at' => now(),
                    'metadata' => [
                        'maker_order_uuid' => $makerOrder->order_uuid,
                        'taker_order_uuid' => $takerOrder->order_uuid,
                        'maker_side' => $makerOrder->side,
                        'taker_side' => $takerOrder->side,
                        'buyer_fee' => $buyFee,
                        'buyer_fee_currency' => $market->base_currency,
                        'buyer_net_base' => $buyNetBase,
                        'seller_fee' => $sellFee,
                        'seller_fee_currency' => $market->quote_currency,
                        'seller_net_quote' => $sellNetQuote,
                    ],
                ]);

                $this->settlements->spotTrade([
                    'buyer_user_id' => $buyOrder->user_id,
                    'seller_user_id' => $sellOrder->user_id,
                    'base_asset' => $market->base_currency,
                    'quote_asset' => $market->quote_currency,
                    'base_amount' => $tradeAmount,
                    'quote_amount' => $quoteAmount,
                    'buyer_fee' => $buyFee,
                    'seller_fee' => $sellFee,
                    'consume_reservations' => [$buyReservationId => $quoteAmount, $sellReservationId => $tradeAmount],
                    'metadata' => [
                        'product' => 'spot',
                        'market' => $market->symbol,
                        'execution_reference' => $trade->trade_uuid,
                        'buy_order_reference' => $buyOrder->order_uuid,
                        'sell_order_reference' => $sellOrder->order_uuid,
                        'maker_order_reference' => $makerOrder->order_uuid,
                        'taker_order_reference' => $takerOrder->order_uuid,
                        'maker_fee_rate' => (string) config('trading.maker_fee', '0.001'),
                        'taker_fee_rate' => (string) config('trading.taker_fee', '0.002'),
                    ],
                ], "spot-fill:{$trade->trade_uuid}");
                if ($this->compare($refund, '0') > 0) {
                    $this->reservations->release($buyReservationId, $refund, ['event' => 'price_improvement', 'trade_uuid' => $trade->trade_uuid]);
                }

                $market->last_price = $executionPrice;
                $market->save();

                $buyTransaction = Transaction::create([
                    'transaction_id' => strtoupper((string) Str::uuid()),
                    'user_id' => $buyOrder->user_id,
                    'type' => TransactionType::Trade,
                    'currency' => $market->base_currency,
                    'amount' => $buyNetBase,
                    'fee' => $buyFee,
                    'status' => TransactionStatus::Completed,
                    'reference' => $buyOrder->order_uuid,
                    'tx_hash' => null,
                    'metadata' => [
                        'trade_uuid' => $trade->trade_uuid,
                        'pair' => $market->symbol,
                        'side' => 'buy',
                        'price' => $executionPrice,
                        'gross_base_received' => $tradeAmount,
                        'quote_spent' => $quoteAmount,
                        'refund' => $refund,
                    ],
                ]);

                $sellTransaction = Transaction::create([
                    'transaction_id' => strtoupper((string) Str::uuid()),
                    'user_id' => $sellOrder->user_id,
                    'type' => TransactionType::Trade,
                    'currency' => $market->quote_currency,
                    'amount' => $sellNetQuote,
                    'fee' => $sellFee,
                    'status' => TransactionStatus::Completed,
                    'reference' => $sellOrder->order_uuid,
                    'tx_hash' => null,
                    'metadata' => [
                        'trade_uuid' => $trade->trade_uuid,
                        'pair' => $market->symbol,
                        'side' => 'sell',
                        'price' => $executionPrice,
                        'base_sold' => $tradeAmount,
                        'gross_quote_received' => $quoteAmount,
                    ],
                ]);

                AuditLog::create([
                    'user_id' => $buyOrder->user_id,
                    'action' => 'trade_executed',
                    'ip_address' => request()?->ip(),
                    'device' => request()?->userAgent(),
                    'metadata' => [
                        'trade_uuid' => $trade->trade_uuid,
                        'pair' => $market->symbol,
                        'side' => 'buy',
                        'amount' => $tradeAmount,
                        'price' => $executionPrice,
                        'fee' => $buyFee,
                    ],
                ]);

                AuditLog::create([
                    'user_id' => $sellOrder->user_id,
                    'action' => 'trade_executed',
                    'ip_address' => request()?->ip(),
                    'device' => request()?->userAgent(),
                    'metadata' => [
                        'trade_uuid' => $trade->trade_uuid,
                        'pair' => $market->symbol,
                        'side' => 'sell',
                        'amount' => $tradeAmount,
                        'price' => $executionPrice,
                        'fee' => $sellFee,
                    ],
                ]);

                $trades[] = $trade->fresh();
                $this->marketStream->publish([
                    'type' => 'trade',
                    'pair' => $market->symbol,
                    'data' => [
                        'trade_uuid' => $trade->trade_uuid,
                        'price' => $executionPrice,
                        'amount' => $tradeAmount,
                        'quote_amount' => $quoteAmount,
                        'maker_fee' => $makerFeeAmount,
                        'taker_fee' => $takerFeeAmount,
                        'timestamp' => $trade->executed_at?->toISOString(),
                    ],
                ]);
            });
        }

        if ($incomingOrder->type === 'market') {
            $fresh = $incomingOrder->fresh();
            if ($this->compare((string) $fresh->locked_amount, '0') > 0) {
                $this->reservations->release(
                    (string) data_get($fresh->metadata, 'reservation_id'),
                    null,
                    ['event' => 'market_order_release', 'order_uuid' => $fresh->order_uuid]
                );
            }
            $fresh->status = $this->compare((string) $fresh->filled_amount, '0') > 0 ? 'filled' : 'cancelled';
            $fresh->remaining_amount = $fresh->status === 'filled' ? '0' : (string) $fresh->remaining_amount;
            $fresh->locked_amount = '0';
            $fresh->save();
        }

        return $trades;
    }

    private function processConditionalOrders(Market $market): void
    {
        while (true) {
            $market->refresh();
            if ($this->compare((string) $market->last_price, '0') <= 0) {
                return;
            }

            $order = Order::query()
                ->where('market_id', $market->id)
                ->where('status', 'pending_trigger')
                ->orderBy('created_at')
                ->get()
                ->first(fn (Order $candidate) => $this->shouldTriggerConditionalOrder($candidate, (string) $market->last_price));

            if (! $order) {
                return;
            }

            $this->triggerConditionalOrder($order, $market);
            $this->refreshOrderBook($market);
        }
    }

    private function triggerConditionalOrder(Order $order, Market $market): void
    {
        DB::transaction(function () use ($order, $market): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->status !== 'pending_trigger') {
                return;
            }

            if (! $this->shouldTriggerConditionalOrder($lockedOrder, (string) $market->last_price)) {
                return;
            }

            $executionType = $lockedOrder->trigger_order_type ?? 'market';
            if ($executionType === 'market' && $lockedOrder->side === 'buy') {
                $requiredQuote = $this->quoteRequiredForBuyAmount($market->id, (string) $lockedOrder->remaining_amount);
                if ($this->compare((string) $lockedOrder->locked_amount, $requiredQuote) < 0) {
                    $this->reservations->release(
                        (string) data_get($lockedOrder->metadata, 'reservation_id'),
                        null,
                        ['event' => 'conditional_order_release', 'order_uuid' => $lockedOrder->order_uuid, 'reason' => 'insufficient_reserved_quote']
                    );

                    $metadata = $lockedOrder->metadata ?? [];
                    $metadata['trigger_failure_reason'] = 'insufficient_reserved_quote';
                    $lockedOrder->status = 'cancelled';
                    $lockedOrder->locked_amount = '0';
                    $lockedOrder->metadata = $metadata;
                    $lockedOrder->save();

                    return;
                }
            }

            $lockedOrder->type = $executionType;
            $lockedOrder->status = 'open';
            $lockedOrder->triggered_at = now();
            $lockedOrder->save();
        });

        $fresh = $order->fresh();
        if ($fresh && $fresh->status === 'open') {
            $this->matchOrder($fresh);
        }
    }

    private function shouldTriggerConditionalOrder(Order $order, string $lastPrice): bool
    {
        if (! in_array($order->type, ['stop_loss', 'take_profit'], true)) {
            return false;
        }

        if ($order->stop_price === null || $this->compare((string) $order->stop_price, '0') <= 0) {
            return false;
        }

        $stopPrice = (string) $order->stop_price;

        return match ([$order->type, $order->side]) {
            ['stop_loss', 'sell'] => $this->compare($lastPrice, $stopPrice) <= 0,
            ['stop_loss', 'buy'] => $this->compare($lastPrice, $stopPrice) >= 0,
            ['take_profit', 'sell'] => $this->compare($lastPrice, $stopPrice) >= 0,
            ['take_profit', 'buy'] => $this->compare($lastPrice, $stopPrice) <= 0,
            default => false,
        };
    }

    private function refreshOrderBook(Market $market): void
    {
        $bids = Order::query()
            ->where('market_id', $market->id)
            ->where('side', 'buy')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderByDesc('price')
            ->orderBy('created_at')
            ->limit(50)
            ->get(['price', 'remaining_amount'])
            ->map(fn (Order $order) => ['price' => (string) $order->price, 'amount' => (string) $order->remaining_amount])
            ->values()
            ->all();

        $asks = Order::query()
            ->where('market_id', $market->id)
            ->where('side', 'sell')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('price')
            ->orderBy('created_at')
            ->limit(50)
            ->get(['price', 'remaining_amount'])
            ->map(fn (Order $order) => ['price' => (string) $order->price, 'amount' => (string) $order->remaining_amount])
            ->values()
            ->all();

        OrderBook::query()->updateOrCreate(
            ['market_id' => $market->id, 'pair' => $market->symbol],
            ['bid_orders' => $bids, 'ask_orders' => $asks, 'last_synced_at' => now()]
        );

        $this->marketStream->publish([
            'type' => 'order_book',
            'pair' => $market->symbol,
            'data' => ['bids' => $bids, 'asks' => $asks, 'timestamp' => now()->toISOString()],
        ]);

        foreach (['1m', '5m', '15m', '1h', '1d'] as $timeframe) {
            $this->marketStream->publish([
                'type' => 'candle',
                'pair' => $market->symbol,
                'timeframe' => $timeframe,
                'data' => array_slice($this->getCandles($market->symbol, $timeframe, 1), -1),
            ]);
        }
    }

    private function getMarket(string $pair): Market
    {
        $normalized = strtoupper(str_replace('-', '/', trim($pair)));

        if (! str_contains($normalized, '/')) {
            foreach (['USDT', 'USDC', 'BTC', 'ETH'] as $quote) {
                if (str_ends_with($normalized, $quote) && strlen($normalized) > strlen($quote)) {
                    $normalized = sprintf('%s/%s', substr($normalized, 0, -strlen($quote)), $quote);
                    break;
                }
            }
        }

        [$base, $quote] = array_pad(explode('/', $normalized, 2), 2, 'USDT');
        $symbol = sprintf('%s/%s', strtoupper($base), strtoupper($quote));

        return Market::query()->firstOrCreate(
            ['symbol' => $symbol],
            [
                'base_currency' => strtoupper($base),
                'quote_currency' => strtoupper($quote),
                'status' => 'active',
                'last_price' => '0',
                'price_precision' => '0.0001',
                'min_order_size' => '0.0001',
                'max_order_size' => '0',
                'maker_fee' => '0.001',
                'taker_fee' => '0.002',
            ]
        );
    }

    private function getBestCounterOrder(int $marketId, string $incomingSide): ?Order
    {
        $query = Order::query()
            ->where('market_id', $marketId)
            ->where('side', $incomingSide === 'buy' ? 'sell' : 'buy')
            ->whereIn('status', ['open', 'partially_filled']);

        return $incomingSide === 'buy'
            ? $query->orderBy('price')->orderBy('created_at')->first()
            : $query->orderByDesc('price')->orderBy('created_at')->first();
    }

    private function quoteRequiredForBuyAmount(int $marketId, string $baseAmount): string
    {
        $remaining = $baseAmount;
        $quoteRequired = '0';
        $orders = Order::query()
            ->where('market_id', $marketId)
            ->where('side', 'sell')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('price')
            ->orderBy('created_at')
            ->get(['price', 'remaining_amount']);

        foreach ($orders as $order) {
            if ($this->compare($remaining, '0') <= 0) {
                break;
            }

            $matchAmount = $this->min($remaining, (string) $order->remaining_amount);
            $quoteRequired = $this->add($quoteRequired, $this->mul($matchAmount, (string) $order->price));
            $remaining = $this->sub($remaining, $matchAmount);
        }

        if ($this->compare($remaining, '0') > 0) {
            throw new RuntimeException('Insufficient order book liquidity for requested size.');
        }

        return $quoteRequired;
    }

    private function buyAmountFromQuoteBudget(int $marketId, string $quoteBudget): string
    {
        $remainingBudget = $quoteBudget;
        $baseAmount = '0';
        $orders = Order::query()
            ->where('market_id', $marketId)
            ->where('side', 'sell')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('price')
            ->orderBy('created_at')
            ->get(['price', 'remaining_amount']);

        foreach ($orders as $order) {
            if ($this->compare($remainingBudget, '0') <= 0) {
                break;
            }

            $fullCost = $this->mul((string) $order->remaining_amount, (string) $order->price);
            if ($this->compare($remainingBudget, $fullCost) >= 0) {
                $baseAmount = $this->add($baseAmount, (string) $order->remaining_amount);
                $remainingBudget = $this->sub($remainingBudget, $fullCost);

                continue;
            }

            $partialBase = $this->div($remainingBudget, (string) $order->price);
            $baseAmount = $this->add($baseAmount, $partialBase);
            $remainingBudget = '0';
            break;
        }

        return $baseAmount;
    }

    private function sumTradeField(array $trades, string $field): string
    {
        $total = '0';
        foreach ($trades as $trade) {
            $total = $this->add($total, (string) data_get($trade, $field, '0'));
        }

        return $total;
    }

    private function sumTradeMetadata(array $trades, string $field): string
    {
        $total = '0';
        foreach ($trades as $trade) {
            $total = $this->add($total, (string) data_get($trade, "metadata.{$field}", '0'));
        }

        return $total;
    }

    private function add(string $a, string $b): string
    {
        return FinancialDecimal::add($a, $b, self::SCALE);
    }

    private function sub(string $a, string $b): string
    {
        return FinancialDecimal::sub($a, $b, self::SCALE);
    }

    private function mul(string $a, string $b): string
    {
        return FinancialDecimal::mul($a, $b, self::SCALE);
    }

    private function div(string $a, string $b): string
    {
        if ($this->compare($b, '0') === 0) {
            throw new RuntimeException('Division by zero.');
        }

        return FinancialDecimal::div($a, $b, self::SCALE);
    }

    private function compare(string $a, string $b): int
    {
        return FinancialDecimal::compare($a, $b, self::SCALE);
    }

    private function min(string $a, string $b): string
    {
        return $this->compare($a, $b) <= 0 ? $a : $b;
    }
}


