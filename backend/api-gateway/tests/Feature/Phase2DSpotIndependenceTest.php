<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CalculateRewardJob;
use App\Models\Market;
use App\Models\SpotExecutionLeg;
use App\Models\SpotExternalVenueAccount;
use App\Models\Trade;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Spot\ExternalSpotVenue;
use App\Services\Spot\SpotLiquidityPolicyService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase2DSpotIndependenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trading.engine.mode', 'new');
        Config::set('trading.liquidity.external_routing_enabled', false);
        Queue::fake([CalculateRewardJob::class]);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();
        Http::fake();

        Market::query()->create([
            'symbol' => 'BTC/USDT',
            'base_currency' => 'BTC',
            'quote_currency' => 'USDT',
            'status' => 'active',
            'trading_status' => 'trading',
            'engine_mode' => 'new',
            'liquidity_mode' => 'INTERNAL_ONLY',
            'price_authority_mode' => 'REFERENCE_ASSISTED',
            'external_routing_enabled' => false,
            'last_price' => '100000',
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

    public function test_two_users_match_inside_exaearn_without_external_venue(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');

        $this->assertCount(1, $result['trades']);
        $this->assertSame('filled', $result['order']->fresh()->status);
        $this->assertSame(1, Trade::query()->where('settlement_status', 'settled')->count());
        Http::assertNothingSent();
    }

    public function test_limit_order_empty_book_rests_as_real_exaearn_liquidity(): void
    {
        $buyer = $this->fundTrading('USDT', '50000');

        $order = app(TradeService::class)
            ->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '95000')['order']
            ->fresh();

        $this->assertSame('open', $order->status);
        $this->assertSame('95000.00000000', (string) $order->price);
        Http::assertNothingSent();
    }

    public function test_market_order_empty_book_internal_only_rejects_without_fake_fill(): void
    {
        $buyer = $this->fundTrading('USDT', '50000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No liquidity available for market order.');

        app(TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'market', '0.1');
    }

    public function test_market_liquidity_policy_is_per_market_and_fail_closed(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $market->update([
            'liquidity_mode' => 'HYBRID',
            'external_routing_enabled' => true,
            'external_routing_policy' => ['shadow_only' => true, 'max_external_percent_per_order' => '50'],
        ]);

        $policy = app(SpotLiquidityPolicyService::class)->policyFor($market->fresh());

        $this->assertSame('HYBRID', $policy['liquidity_mode']);
        $this->assertTrue($policy['shadow_only']);
        $this->assertFalse(app(SpotLiquidityPolicyService::class)->canUseExternalFallback($market->fresh()));

        Config::set('trading.liquidity.external_routing_enabled', true);
        $market->update(['external_routing_policy' => ['shadow_only' => false, 'max_external_percent_per_order' => '50']]);

        $this->assertTrue(app(SpotLiquidityPolicyService::class)->canUseExternalFallback($market->fresh()));
    }

    public function test_hybrid_market_order_uses_external_fallback_only_after_internal_book_first(): void
    {
        Config::set('trading.liquidity.external_routing_enabled', true);
        Config::set('services.binance.simulate', true);
        $this->bindFakeExternalVenue();

        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $market->update([
            'liquidity_mode' => 'HYBRID',
            'external_routing_enabled' => true,
            'external_routing_policy' => ['shadow_only' => false, 'venue' => 'BINANCE'],
        ]);
        SpotExternalVenueAccount::query()->create([
            'venue' => 'BINANCE',
            'asset' => 'BTC',
            'available_balance' => '1',
            'locked_balance' => '0',
            'status' => 'active',
        ]);

        $buyer = $this->fundTrading('USDT', '50000');
        $result = app(TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'market', '0.1');

        $this->assertSame('filled', $result['order']->fresh()->status);
        $this->assertCount(1, $result['external_executions']);
        $this->assertSame(0, Trade::query()->count());
        $this->assertSame(1, SpotExecutionLeg::query()->where('venue', 'BINANCE')->where('status', 'settled')->count());
    }

    public function test_hybrid_external_fallback_rejects_when_venue_inventory_is_not_funded(): void
    {
        Config::set('trading.liquidity.external_routing_enabled', true);
        $this->bindFakeExternalVenue();

        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $market->update([
            'liquidity_mode' => 'HYBRID',
            'external_routing_enabled' => true,
            'external_routing_policy' => ['shadow_only' => false, 'venue' => 'BINANCE'],
        ]);
        $buyer = $this->fundTrading('USDT', '50000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EXTERNAL_VENUE_BALANCE_INSUFFICIENT');

        app(TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'market', '0.1');
    }

    private function fundTrading(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase2d-seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, "phase2d-trading-{$user->id}-{$asset}");

        return $user;
    }

    private function bindFakeExternalVenue(): void
    {
        $this->app->bind(ExternalSpotVenue::class, fn (): ExternalSpotVenue => new class implements ExternalSpotVenue {
            public function venueCode(): string
            {
                return 'BINANCE';
            }

            public function healthCheck(): array
            {
                return ['healthy' => true];
            }

            public function getMarkets(): array
            {
                return ['BTCUSDT'];
            }

            public function getTicker(string $symbol): array
            {
                return ['symbol' => $symbol, 'price' => '100000.00'];
            }

            public function getOrderBook(string $symbol, int $limit = 20): array
            {
                return [
                    'bids' => [['price' => '99900.00', 'amount' => '1.0']],
                    'asks' => [['price' => '100000.00', 'amount' => '1.0']],
                ];
            }

            public function getBalance(string $asset): array
            {
                return ['asset' => $asset, 'available' => '1'];
            }

            public function placeOrder(array $order): array
            {
                return [
                    'status' => 'filled',
                    'source' => 'fake_external_venue',
                    'id' => 'fake-external-order-1',
                    'executed_qty' => (string) $order['quantity'],
                    'executed_price' => (string) $order['price'],
                ];
            }

            public function cancelOrder(string $symbol, string $externalOrderId): array
            {
                return ['status' => 'cancelled', 'symbol' => $symbol, 'id' => $externalOrderId];
            }

            public function getOrder(string $symbol, string $externalOrderId): array
            {
                return ['status' => 'filled', 'symbol' => $symbol, 'id' => $externalOrderId];
            }

            public function getTrades(string $symbol, string $externalOrderId): array
            {
                return [];
            }
        });
    }
}
