<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CalculateRewardJob;
use App\Models\LedgerTransaction;
use App\Models\Market;
use App\Models\SpotEngineSequence;
use App\Models\SpotEngineLoadRun;
use App\Models\SpotExecutionEvent;
use App\Models\SpotMarketDataEvent;
use App\Models\SpotMarketEngineLease;
use App\Models\SpotSettlementOutbox;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Spot\MarketEngineLeaseService;
use App\Services\Spot\MatchingEngineReplayService;
use App\Services\Spot\SettlementOutboxService;
use App\Services\Spot\ShadowComparisonService;
use App\Services\Spot\SpotRealtimeSequenceService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase2BAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trading.engine.mode', 'new');
        Config::set('trading.engine.lease_ttl_seconds', 1);
        Queue::fake([CalculateRewardJob::class]);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $this->market('BTC/USDT', 'BTC', 'USDT');
    }

    public function test_market_lease_fencing_and_failover(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $service = app(MarketEngineLeaseService::class);

        $a = $service->acquire($market, 'engine-a');
        $this->expectException(RuntimeException::class);
        $service->acquire($market, 'engine-b');
    }

    public function test_stale_fencing_token_is_rejected_after_takeover(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $service = app(MarketEngineLeaseService::class);

        $a = $service->acquire($market, 'engine-a');
        SpotMarketEngineLease::query()->whereKey($a->id)->update(['expires_at' => now()->subSecond()]);
        $b = $service->acquire($market, 'engine-b');

        $this->assertGreaterThan((int) $a->generation, (int) $b->generation);
        $this->expectException(RuntimeException::class);
        $service->assertCurrent($market, (string) $a->lease_token, (int) $a->generation, 'engine-a');
    }

    public function test_replay_gap_detection_halts_market(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        SpotExecutionEvent::query()->create([
            'event_id' => fake()->uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'sequence' => 2,
            'event_type' => 'ORDER_OPENED',
            'payload' => ['order_uuid' => 'x', 'side' => 'buy', 'price' => '1', 'remaining_amount' => '1', 'sequence' => 2],
            'occurred_at' => now(),
        ]);

        try {
            app(MatchingEngineReplayService::class)->replay($market);
            $this->fail('Replay should detect a sequence gap.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SEQUENCE GAP', $exception->getMessage());
        }
        $this->assertSame('halted', $market->fresh()->trading_status);
    }

    public function test_realtime_gap_detection_requires_resync(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        SpotMarketDataEvent::query()->create([
            'event_id' => fake()->uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'sequence' => 2,
            'event_type' => 'BOOK_DELTA',
            'payload' => [],
            'occurred_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        app(SpotRealtimeSequenceService::class)->deltasAfter($market, 0);
    }

    public function test_settlement_outbox_retry_is_logically_exactly_once(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');
        $trade = $result['trades'][0];
        $outbox = SpotSettlementOutbox::query()->where('trade_id', $trade->id)->firstOrFail();
        $outbox->status = 'pending';
        $outbox->settled_at = null;
        $outbox->save();

        app(SettlementOutboxService::class)->processOne($outbox);
        app(SettlementOutboxService::class)->processOne($outbox->fresh());

        $this->assertSame('settled', $outbox->fresh()->status);
        $this->assertSame(1, LedgerTransaction::query()->where('reference', $outbox->reference)->count());
    }

    public function test_load_harness_records_correctness_run(): void
    {
        $this->artisan('spot:load-harness', ['--orders' => 10, '--market' => 'LDB/USDT'])->assertSuccessful();

        $run = SpotEngineLoadRun::query()->where('market_symbol', 'LDB/USDT')->latest()->firstOrFail();
        $this->assertSame(10, (int) $run->orders_submitted);
        $this->assertSame(10, (int) $run->orders_accepted);
        $this->assertSame(0, (int) $run->error_count);
    }

    public function test_shadow_comparison_records_match_and_policy_difference(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $service = app(ShadowComparisonService::class);

        $match = $service->compare($market, ['accepted' => true, 'status' => 'open', 'fills_count' => 0], ['accepted' => true, 'status' => 'open', 'fills_count' => 0]);
        $policy = $service->compare($market, ['accepted' => true, 'status' => 'open', 'fills_count' => 0], ['accepted' => true, 'status' => 'rejected', 'fills_count' => 0]);

        $this->assertSame('MATCH', $match->classification);
        $this->assertSame('EXPECTED_POLICY_DIFFERENCE', $policy->classification);
    }

    public function test_multi_market_sequences_and_leases_are_isolated(): void
    {
        $this->market('ETH/USDT', 'ETH', 'USDT');

        $btcSeller = $this->fundTrading('BTC', '1');
        $btcBuyer = $this->fundTrading('USDT', '100000');
        $ethSeller = $this->fundTrading('ETH', '10');
        $ethBuyer = $this->fundTrading('USDT', '100000');
        $service = app(TradeService::class);

        $service->placeOrder($btcSeller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $service->placeOrder($btcBuyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');
        $service->placeOrder($ethSeller->id, 'ETH/USDT', 'sell', 'limit', '1', '3000');
        $service->placeOrder($ethBuyer->id, 'ETH/USDT', 'buy', 'limit', '1', '3000');

        $btc = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $eth = Market::query()->where('symbol', 'ETH/USDT')->firstOrFail();

        $this->assertSame(2, (int) SpotEngineSequence::query()->where('market_id', $btc->id)->firstOrFail()->last_sequence);
        $this->assertSame(2, (int) SpotEngineSequence::query()->where('market_id', $eth->id)->firstOrFail()->last_sequence);
        $this->assertSame(1, SpotMarketEngineLease::query()->where('market_id', $btc->id)->count());
        $this->assertSame(1, SpotMarketEngineLease::query()->where('market_id', $eth->id)->count());
    }

    private function market(string $symbol, string $base, string $quote): void
    {
        Market::query()->create([
            'symbol' => $symbol,
            'base_currency' => $base,
            'quote_currency' => $quote,
            'status' => 'active',
            'trading_status' => 'trading',
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

    private function fundTrading(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase2b-seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, "phase2b-trading-{$user->id}-{$asset}");

        return $user;
    }
}
