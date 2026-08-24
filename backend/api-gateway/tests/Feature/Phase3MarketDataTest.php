<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CalculateRewardJob;
use App\Models\Market;
use App\Models\SpotMarketDataEvent;
use App\Models\Trade;
use App\Services\LedgerService;
use App\Services\MarketDataService;
use App\Services\Spot\SpotRealtimeSequenceService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase3MarketDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('trading.engine.mode', 'new');
        Queue::fake([CalculateRewardJob::class]);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();
        Http::fake([
            'api.binance.com/api/v3/ticker/24hr*' => Http::response([
                'lastPrice' => '101000.50',
                'openPrice' => '100000.00',
                'priceChange' => '1000.50',
                'priceChangePercent' => '1.0005',
                'highPrice' => '102000.00',
                'lowPrice' => '99000.00',
                'volume' => '12.5',
                'quoteVolume' => '1262500',
                'count' => 44,
            ]),
            'api.binance.com/api/v3/depth*' => Http::response([
                'bids' => [['100900.00', '0.25']],
                'asks' => [['101100.00', '0.30']],
            ]),
            'api.binance.com/api/v3/trades*' => Http::response([
                ['id' => 1, 'price' => '101000.00', 'qty' => '0.01', 'time' => 1785463000000, 'isBuyerMaker' => false],
            ]),
            'api.binance.com/api/v3/klines*' => Http::response([
                [1785462960000, '100000.00', '101000.00', '99900.00', '100500.00', '0.05', 1785463019999, '5025.00', 3],
            ]),
        ]);

        $this->market('BTC/USDT', 'BTC', 'USDT');
        $this->market('ETH/USDT', 'ETH', 'USDT');
    }

    public function test_internal_order_book_comes_from_exaearn_resting_orders(): void
    {
        $buyer = $this->fundTrading('USDT', '50000');
        $seller = $this->fundTrading('BTC', '1');

        app(TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '99000');
        app(TradeService::class)->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '101000');

        $book = app(MarketDataService::class)->orderBook('BTCUSDT', 10);

        $this->assertSame(MarketDataService::SOURCE_INTERNAL, $book['source']);
        $this->assertTrue($book['is_internal']);
        $this->assertSame('99000.00000000', $book['bids'][0]['price']);
        $this->assertSame('101000.00000000', $book['asks'][0]['price']);
    }

    public function test_recent_trades_ticker_and_candles_use_exaearn_executions(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');

        app(TradeService::class)->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        app(TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');

        $service = app(MarketDataService::class);
        $trade = $service->recentTrades('BTC/USDT', 10)[0];
        $ticker = $service->ticker('BTCUSDT');
        $candles = $service->candles('BTC-USDT', '1m', 10);

        $this->assertSame(MarketDataService::SOURCE_INTERNAL, $trade['source']);
        $this->assertSame('100000.00000000', $ticker['last_price']);
        $this->assertSame(MarketDataService::SOURCE_INTERNAL, $ticker['source']);
        $this->assertSame(MarketDataService::SOURCE_INTERNAL, $candles[0]['source']);
        $this->assertSame('0.100000000000000000', $ticker['base_volume_24h']);
    }

    public function test_reference_fallback_is_explicit_when_no_exaearn_execution_exists(): void
    {
        $ticker = app(MarketDataService::class)->ticker('ETHUSDT');
        $book = app(MarketDataService::class)->orderBook('ETH/USDT', 5);
        $trades = app(MarketDataService::class)->recentTrades('ETH/USDT', 5);
        $candles = app(MarketDataService::class)->candles('ETH/USDT', '1m', 5);

        $this->assertSame(MarketDataService::SOURCE_REFERENCE, $ticker['source']);
        $this->assertSame('101000.50', $ticker['reference_price']);
        $this->assertSame(MarketDataService::SOURCE_REFERENCE, $book['source']);
        $this->assertFalse($book['is_internal']);
        $this->assertSame(MarketDataService::SOURCE_REFERENCE, $trades[0]['source']);
        $this->assertSame(MarketDataService::SOURCE_REFERENCE, $candles[0]['source']);
    }

    public function test_public_rest_market_contracts_are_available_without_authentication(): void
    {
        $this->getJson('/api/v1/market/symbols')->assertOk()->assertJsonPath('data.0.symbol', 'BTC/USDT');
        $this->getJson('/api/v1/market/tickers')->assertOk()->assertJsonStructure(['data' => [['symbol', 'last_price', 'source']]]);
        $this->getJson('/api/v1/market/order-book/BTC-USDT')->assertOk()->assertJsonStructure(['data' => ['symbol', 'bids', 'asks', 'source']]);
        $this->getJson('/api/v1/market/trades/BTC-USDT')->assertOk();
        $this->getJson('/api/v1/market/klines/BTC-USDT?interval=1m')->assertOk();
    }

    public function test_exchange_aggregator_compatible_market_aliases_are_public(): void
    {
        $this->getJson('/api/v1/markets')
            ->assertOk()
            ->assertJsonStructure(['data' => [['trading_pair', 'symbol', 'base_asset', 'quote_asset', 'market_type', 'market_status', 'source', 'timestamp']]]);

        $this->getJson('/api/v1/ticker')->assertOk()->assertJsonStructure(['data' => [['symbol', 'source']]]);
        $this->getJson('/api/v1/ticker/24hr')->assertOk()->assertJsonStructure(['data' => [['trading_pair', 'quote_volume_24h']]]);
        $this->getJson('/api/v1/orderbook?pair=BTC-USDT')->assertOk()->assertJsonStructure(['data' => ['symbol', 'bids', 'asks', 'source']]);
        $this->getJson('/api/v1/trades?pair=BTC-USDT')->assertOk();
    }

    public function test_realtime_deltas_allow_multiple_events_at_same_sequence_and_detect_true_gaps(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();

        SpotMarketDataEvent::query()->create([
            'event_id' => fake()->uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'sequence' => 1,
            'event_type' => 'BOOK_DELTA',
            'payload' => [],
            'occurred_at' => now(),
        ]);
        SpotMarketDataEvent::query()->create([
            'event_id' => fake()->uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'sequence' => 1,
            'event_type' => 'BEST_BID_ASK',
            'payload' => [],
            'occurred_at' => now(),
        ]);

        $this->assertCount(2, app(SpotRealtimeSequenceService::class)->deltasAfter($market, 0));

        SpotMarketDataEvent::query()->create([
            'event_id' => fake()->uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'sequence' => 3,
            'event_type' => 'BOOK_DELTA',
            'payload' => [],
            'occurred_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        app(SpotRealtimeSequenceService::class)->deltasAfter($market, 1);
    }

    public function test_multi_market_ticker_isolation(): void
    {
        $btcSeller = $this->fundTrading('BTC', '1');
        $btcBuyer = $this->fundTrading('USDT', '50000');
        $ethSeller = $this->fundTrading('ETH', '10');
        $ethBuyer = $this->fundTrading('USDT', '50000');

        app(TradeService::class)->placeOrder($btcSeller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        app(TradeService::class)->placeOrder($btcBuyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');
        app(TradeService::class)->placeOrder($ethSeller->id, 'ETH/USDT', 'sell', 'limit', '1', '3000');
        app(TradeService::class)->placeOrder($ethBuyer->id, 'ETH/USDT', 'buy', 'limit', '1', '3000');

        $this->assertSame('100000.00000000', app(MarketDataService::class)->ticker('BTC/USDT')['last_price']);
        $this->assertSame('3000.00000000', app(MarketDataService::class)->ticker('ETH/USDT')['last_price']);
        $this->assertSame(1, Trade::query()->where('pair', 'BTC/USDT')->count());
        $this->assertSame(1, Trade::query()->where('pair', 'ETH/USDT')->count());
    }

    private function market(string $symbol, string $base, string $quote): void
    {
        Market::query()->create([
            'symbol' => $symbol,
            'base_currency' => $base,
            'quote_currency' => $quote,
            'status' => 'active',
            'trading_status' => 'trading',
            'last_price' => '0',
            'price_precision' => '0.01',
            'tick_size' => '0.01',
            'quantity_step' => '0.0001',
            'min_order_size' => '0.0001',
            'max_order_size' => '100',
            'min_notional' => '10',
            'max_notional' => '0',
            'maker_fee' => '0.001',
            'taker_fee' => '0.002',
        ]);
    }

    private function fundTrading(string $asset, string $amount)
    {
        $user = \App\Models\User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase3-seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, "phase3-trading-{$user->id}-{$asset}");

        return $user;
    }
}
