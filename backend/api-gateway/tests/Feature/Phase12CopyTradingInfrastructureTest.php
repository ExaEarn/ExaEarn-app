<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessCopyFollowerDecision;
use App\Models\CopyLeadTradeEvent;
use App\Models\CopyLoadRun;
use App\Models\CopyOrder;
use App\Models\CopyProfitShareAccrual;
use App\Models\CopyRelationship;
use App\Models\CopyRealtimeEvent;
use App\Models\FuturesMarket;
use App\Models\FuturesOrder;
use App\Models\Market;
use App\Models\Order;
use App\Models\CopyStrategyPosition;
use App\Models\CopySurveillanceCase;
use App\Models\Trader;
use App\Models\User;
use App\Services\CopyLoadTestService;
use App\Services\CopyRealtimeService;
use App\Services\CopyTradingOperationalReadinessService;
use App\Services\CopyTradingService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class Phase12CopyTradingInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private FuturesMarket $market;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('futures.allow_external_execution', false);
        Config::set('futures.engine_mode', 'new');
        Config::set('trading.engine.mode', 'new');
        Config::set('trading_operations.default_max_order_notional', '1000000');
        Config::set('trading_operations.default_max_leverage', 100);
        Config::set('copy_trading.max_event_age_seconds', 300);
        Config::set('queue.default', 'null');
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $this->market = FuturesMarket::query()->create([
            'symbol' => 'BTCUSDT',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'settlement_asset' => 'USDT',
            'contract_type' => 'PERPETUAL',
            'status' => 'active',
            'engine_mode' => 'new',
            'min_leverage' => 1,
            'max_leverage' => 100,
            'maintenance_margin_rate' => '0.005',
            'last_price' => '50000',
            'index_price' => '50000',
            'mark_price' => '50000',
            'funding_rate' => '0',
            'tick_size' => '0.01',
            'quantity_step' => '0.0001',
            'min_quantity' => '0.0001',
            'max_quantity' => '100',
            'min_notional' => '5',
            'max_notional' => '1000000',
            'risk_tiers' => [
                ['notional_floor' => '0', 'notional_cap' => '50000', 'maintenance_margin_rate' => '0.005', 'maintenance_amount' => '0', 'max_leverage' => 100],
            ],
            'price_band_bps' => 500,
        ]);

        Market::query()->create([
            'symbol' => 'BTC/USDT',
            'base_currency' => 'BTC',
            'quote_currency' => 'USDT',
            'status' => 'active',
            'trading_status' => 'trading',
            'last_price' => '50000',
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

    public function test_lead_application_approval_and_follower_allocation_are_recorded(): void
    {
        [$lead, $follower] = [User::factory()->create(), $this->fundFutures('10000')];
        $copyTrading = app(CopyTradingService::class);

        $trader = $copyTrading->applyLeadTrader($lead->id, [
            'display_name' => 'ExaEarn Lead',
            'supported_products' => ['futures'],
            'profit_share_rate' => '0.15',
        ]);
        $approved = $copyTrading->activateLeadTrader($trader->id, User::factory()->create()->id);
        $relationship = $copyTrading->followTrader($follower->id, $approved->id, '1000', 'medium', [
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '100',
            'max_leverage' => 5,
        ]);

        $this->assertSame('active', $approved->status);
        $this->assertSame('active', $relationship->status);
        $this->assertSame('1000.000000000000000000', (string) $relationship->high_water_mark);
        $this->assertDatabaseHas('traders', ['id' => $approved->id, 'is_master_trader' => true, 'status' => 'active']);
    }

    public function test_futures_lead_execution_creates_real_follower_order_with_leverage_cap(): void
    {
        [$trader, $relationship] = $this->activeRelationship([
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '100',
            'max_leverage' => 3,
        ]);

        $event = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'futures',
            'symbol' => 'BTCUSDT',
            'side' => 'long',
            'lead_trade_id' => 'lead-fill-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.1',
            'leverage' => 20,
            'margin_mode' => 'cross',
            'metadata' => ['lead_strategy_equity' => '5000'],
        ]);

        $orders = app(CopyTradingService::class)->fanoutLeadExecution($event);
        $copyOrder = $orders[0]->fresh();
        $followerOrder = FuturesOrder::query()->findOrFail($copyOrder->follower_futures_order_id);

        $this->assertSame('executing', $copyOrder->status);
        $this->assertSame('0.002000000000000000', (string) $copyOrder->target_quantity);
        $this->assertSame('copy_trading', $followerOrder->source);
        $this->assertSame(3, $followerOrder->leverage);
        $this->assertSame('open', $followerOrder->status);
        $this->assertStringStartsWith('copy:', (string) $followerOrder->client_order_id);
        $this->assertDatabaseHas('reservations', [
            'user_id' => $relationship->follower_id,
            'purpose' => 'futures_initial_margin',
            'reference_type' => 'futures_order',
            'status' => 'active',
        ]);
    }

    public function test_duplicate_lead_trade_event_is_idempotent_and_does_not_duplicate_copy_orders(): void
    {
        [$trader] = $this->activeRelationship();
        $payload = [
            'product' => 'futures',
            'symbol' => 'BTCUSDT',
            'side' => 'long',
            'lead_trade_id' => 'same-fill',
            'execution_price' => '50000',
            'executed_quantity' => '0.1',
            'leverage' => 2,
        ];

        $first = app(CopyTradingService::class)->recordLeadExecution($trader->id, $payload);
        $second = app(CopyTradingService::class)->recordLeadExecution($trader->id, $payload);
        app(CopyTradingService::class)->fanoutLeadExecution($first);
        app(CopyTradingService::class)->fanoutLeadExecution($second);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CopyLeadTradeEvent::query()->where('lead_trade_id', 'same-fill')->count());
        $this->assertSame(1, CopyOrder::query()->where('lead_trade_event_id', $first->id)->count());
    }

    public function test_unsupported_copy_product_is_not_fabricated_through_futures_engine(): void
    {
        [$trader] = $this->activeSpotRelationship(['product_scope' => 'all']);

        $event = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'options',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'lead_trade_id' => 'unsupported-fill-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.01',
        ]);

        $orders = app(CopyTradingService::class)->fanoutLeadExecution($event);

        $this->assertSame('skipped', $orders[0]->status);
        $this->assertSame('PRODUCT_NOT_SUPPORTED', $orders[0]->reason_code);
        $this->assertSame(0, FuturesOrder::query()->where('source', 'copy_trading')->count());
    }

    public function test_spot_lead_buy_creates_real_follower_spot_order_fill_and_attribution(): void
    {
        [$trader, $relationship] = $this->activeSpotRelationship([
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '100',
            'allowed_symbols' => ['BTCUSDT'],
        ]);
        $seller = $this->fundUnified('BTC', '1');
        app(\App\Services\TradeService::class)->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.01', '50000');

        $event = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'lead_trade_id' => 'spot-buy-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.01',
            'metadata' => ['base_asset' => 'BTC', 'quote_asset' => 'USDT'],
        ]);

        $copyOrder = app(CopyTradingService::class)->fanoutLeadExecution($event)[0]->fresh();

        $this->assertSame('filled', $copyOrder->status);
        $this->assertNotNull($copyOrder->follower_spot_order_id);
        $this->assertSame('copy_trading', data_get(Order::findOrFail($copyOrder->follower_spot_order_id)->metadata, 'source'));
        $this->assertDatabaseHas('trades', ['buy_order_id' => $copyOrder->follower_spot_order_id, 'settlement_status' => 'settled']);
        $this->assertDatabaseHas('copy_strategy_positions', [
            'copy_relationship_id' => $relationship->id,
            'product' => 'spot',
            'symbol' => 'BTC/USDT',
            'asset' => 'BTC',
            'side' => 'long',
        ]);
        $this->assertGreaterThanOrEqual(1, CopyRealtimeEvent::query()->where('user_id', $relationship->follower_id)->where('event_type', 'copy.fill')->count());
    }

    public function test_spot_lead_sell_sells_only_attributed_copy_holdings_not_manual_assets(): void
    {
        [$trader, $relationship, $follower] = $this->activeSpotRelationship([
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '100',
        ]);
        $this->fundExistingUnified($follower, 'BTC', '2');
        $seller = $this->fundUnified('BTC', '1');
        $buyer = $this->fundUnified('USDT', '10000');
        app(\App\Services\TradeService::class)->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.01', '50000');

        $buyEvent = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'lead_trade_id' => 'spot-buy-before-sell',
            'execution_price' => '50000',
            'executed_quantity' => '0.01',
        ]);
        app(CopyTradingService::class)->fanoutLeadExecution($buyEvent);
        app(\App\Services\TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.01', '50000');

        $sellEvent = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'sell',
            'position_effect' => 'partial_close',
            'lead_trade_id' => 'spot-sell-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.005',
            'metadata' => ['lead_close_ratio' => '0.5'],
        ]);
        $sellCopy = app(CopyTradingService::class)->fanoutLeadExecution($sellEvent)[0]->fresh();
        $position = CopyStrategyPosition::query()->where('copy_relationship_id', $relationship->id)->where('product', 'spot')->firstOrFail();

        $this->assertSame('filled', $sellCopy->status);
        $this->assertSame('0.001000000000000000', (string) $position->remaining_quantity);
        $this->assertGreaterThan(0, (float) app(\App\Services\BalanceProjectionService::class)->byUserAccountAndAsset($follower->id, 'unified_trading', 'BTC')['total']);
    }

    public function test_spot_slippage_skip_duplicate_event_realtime_replay_and_stale_opening(): void
    {
        [$trader, $relationship] = $this->activeSpotRelationship([
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '100',
        ]);
        $seller = $this->fundUnified('BTC', '1');
        app(\App\Services\TradeService::class)->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.01', '51000');

        $event = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'lead_trade_id' => 'spot-slippage-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.01',
            'metadata' => ['max_copy_slippage_bps' => '100'],
        ]);
        $same = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'lead_trade_id' => 'spot-slippage-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.01',
        ]);
        $copy = app(CopyTradingService::class)->fanoutLeadExecution($event)[0]->fresh();
        app(CopyTradingService::class)->fanoutLeadExecution($same);

        $stale = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'lead_trade_id' => 'spot-stale-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.01',
            'executed_at' => now()->subMinutes(10),
        ]);
        $staleCopy = app(CopyTradingService::class)->fanoutLeadExecution($stale)[0]->fresh();
        $events = app(CopyRealtimeService::class)->replay((int) $relationship->follower_id, 0);

        $this->assertSame($event->id, $same->id);
        $this->assertSame('skipped', $copy->status);
        $this->assertSame('SKIPPED_SLIPPAGE_LIMIT', $copy->reason_code);
        $this->assertSame(1, CopyOrder::query()->where('lead_trade_event_id', $event->id)->count());
        $this->assertSame('SKIPPED_STALE_EVENT', $staleCopy->reason_code);
        $this->assertSame(range(1, count($events)), array_column($events, 'sequence'));
    }

    public function test_capacity_surveillance_and_load_run_records_are_operational(): void
    {
        Config::set('copy_trading.max_followers_per_lead', 1);
        [$trader, $relationship] = $this->activeRelationship();
        $second = $this->fundFutures('10000');

        $this->expectException(\RuntimeException::class);
        try {
            app(CopyTradingService::class)->followTrader($second->id, $trader->id, '1000');
        } finally {
            $event = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
                'product' => 'futures',
                'symbol' => 'BTCUSDT',
                'side' => 'long',
                'lead_trade_id' => 'load-run-1',
                'execution_price' => '50000',
                'executed_quantity' => '0.1',
                'leverage' => 2,
            ]);
            app(CopyTradingService::class)->fanoutLeadExecution($event);
            $run = app(CopyLoadTestService::class)->recordFanoutRun($event, 'unit_1k_10k_gate_harness');

            $this->assertDatabaseHas('copy_load_runs', ['id' => $run->id, 'status' => 'PASS']);
            $this->assertGreaterThanOrEqual(1, CopyLoadRun::query()->count());
            $this->assertGreaterThanOrEqual(0, CopySurveillanceCase::query()->count());
            $this->assertSame('active', $relationship->status);
        }
    }

    public function test_copy_fanout_can_queue_follower_decisions_with_priority(): void
    {
        Queue::fake([ProcessCopyFollowerDecision::class]);
        [$trader] = $this->activeRelationship();

        $event = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'futures',
            'symbol' => 'BTCUSDT',
            'side' => 'short',
            'position_effect' => 'close',
            'lead_trade_id' => 'queued-close-1',
            'execution_price' => '50000',
            'executed_quantity' => '0.1',
            'leverage' => 2,
        ]);

        $queued = app(CopyTradingService::class)->queueFanoutLeadExecution($event);

        $this->assertSame(1, $queued);
        Queue::assertPushed(ProcessCopyFollowerDecision::class, 1);
    }

    public function test_1k_10k_and_mass_close_queue_fanout_dispatches_without_duplicates(): void
    {
        $lead = User::factory()->create();
        $trader = app(CopyTradingService::class)->applyLeadTrader($lead->id, ['display_name' => 'Load Lead']);
        $trader = app(CopyTradingService::class)->activateLeadTrader($trader->id, User::factory()->create()->id);

        $this->bulkFollowers($trader, 10000);
        $event = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'futures',
            'symbol' => 'BTCUSDT',
            'side' => 'long',
            'lead_trade_id' => 'queue-load-10k',
            'execution_price' => '50000',
            'executed_quantity' => '0.1',
            'leverage' => 2,
        ]);

        $queued = app(CopyTradingService::class)->queueFanoutLeadExecution($event, 1000, false);

        $fullClose = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'futures',
            'symbol' => 'BTCUSDT',
            'side' => 'short',
            'position_effect' => 'close',
            'lead_trade_id' => 'queue-load-10k-full-close',
            'execution_price' => '50000',
            'executed_quantity' => '0.1',
            'leverage' => 2,
        ]);
        $fullCloseQueued = app(CopyTradingService::class)->queueFanoutLeadExecution($fullClose, 1000, false);

        $partialClose = app(CopyTradingService::class)->recordLeadExecution($trader->id, [
            'product' => 'futures',
            'symbol' => 'BTCUSDT',
            'side' => 'short',
            'position_effect' => 'partial_close',
            'lead_trade_id' => 'queue-load-10k-partial-close',
            'execution_price' => '50000',
            'executed_quantity' => '0.025',
            'leverage' => 2,
        ]);
        $partialCloseQueued = app(CopyTradingService::class)->queueFanoutLeadExecution($partialClose, 1000, false);

        $this->assertSame(10000, $queued);
        $this->assertSame(10000, $fullCloseQueued);
        $this->assertSame(10000, $partialCloseQueued);
        $this->assertSame(10000, CopyRelationship::query()->where('trader_id', $trader->id)->distinct('follower_id')->count('follower_id'));
    }

    public function test_profit_share_uses_high_water_mark_and_only_accrues_new_profit(): void
    {
        [, $relationship] = $this->activeRelationship();
        $copyTrading = app(CopyTradingService::class);

        $first = $copyTrading->accrueProfitShare($relationship, '1200');
        $none = $copyTrading->accrueProfitShare($relationship->fresh(), '1150');
        $second = $copyTrading->accrueProfitShare($relationship->fresh(), '1300');

        $this->assertInstanceOf(CopyProfitShareAccrual::class, $first);
        $this->assertNull($none);
        $this->assertInstanceOf(CopyProfitShareAccrual::class, $second);
        $this->assertSame('20.000000000000000000', (string) $first->accrued_amount);
        $this->assertSame('10.000000000000000000', (string) $second->accrued_amount);
        $this->assertSame('1300.000000000000000000', (string) $relationship->fresh()->high_water_mark);
    }

    public function test_operational_readiness_reports_copy_trading_components(): void
    {
        $this->activeRelationship();

        $readiness = app(CopyTradingOperationalReadinessService::class)->check();

        $this->assertSame('READY', $readiness['status']);
        $this->assertTrue($readiness['checks']['lead_execution_events']);
        $this->assertTrue($readiness['checks']['copy_orders']);
        $this->assertGreaterThanOrEqual(1, $readiness['active_lead_traders']);
        $this->assertGreaterThanOrEqual(1, $readiness['active_followers']);
    }

    private function activeRelationship(array $settings = []): array
    {
        $lead = User::factory()->create();
        $follower = $this->fundFutures('10000');
        $copyTrading = app(CopyTradingService::class);
        $trader = $copyTrading->applyLeadTrader($lead->id, [
            'display_name' => 'Phase 12 Lead',
            'supported_products' => ['futures'],
            'profit_share_rate' => '0.10',
        ]);
        $trader = $copyTrading->activateLeadTrader($trader->id, User::factory()->create()->id);
        $relationship = $copyTrading->followTrader($follower->id, $trader->id, '1000', 'medium', array_merge([
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '100',
            'max_amount_per_trade' => '200',
            'max_leverage' => 5,
            'margin_preference' => 'isolated',
            'allowed_symbols' => ['BTCUSDT'],
        ], $settings));

        return [$trader, $relationship, $follower];
    }

    private function activeSpotRelationship(array $settings = []): array
    {
        $lead = User::factory()->create(['email' => 'lead' . uniqid() . '@exaearn.test']);
        $follower = $this->fundUnified('USDT', '10000');
        $copyTrading = app(CopyTradingService::class);
        $trader = $copyTrading->applyLeadTrader($lead->id, [
            'display_name' => 'Phase 12 Spot Lead',
            'supported_products' => ['spot'],
            'profit_share_rate' => '0.10',
        ]);
        $trader = $copyTrading->activateLeadTrader($trader->id, User::factory()->create()->id);
        $relationship = $copyTrading->followTrader($follower->id, $trader->id, '1000', 'medium', array_merge([
            'product_scope' => 'spot',
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '100',
            'max_amount_per_trade' => '200',
            'allowed_symbols' => ['BTCUSDT'],
        ], $settings));

        return [$trader, $relationship, $follower];
    }

    private function fundFutures(string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, 'USDT', "phase12-seed-{$user->id}");
        $ledger->internalTransfer($user->id, 'funding', 'futures', $amount, 'USDT', "phase12-futures-{$user->id}");

        return $user;
    }

    private function fundUnified(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $this->fundExistingUnified($user, $asset, $amount);

        return $user;
    }

    private function fundExistingUnified(User $user, string $asset, string $amount): void
    {
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase12-spot-seed-{$user->id}-{$asset}-" . uniqid());
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, "phase12-spot-trading-{$user->id}-{$asset}-" . uniqid());
    }

    private function bulkFollowers(Trader $trader, int $count): void
    {
        $start = ((int) DB::table('users')->max('id')) + 1;
        $now = now();
        $users = [];
        $relationships = [];

        for ($i = 0; $i < $count; $i++) {
            $userId = $start + $i;
            $users[] = [
                'id' => $userId,
                'unique_user_id' => 'LOAD' . str_pad((string) $userId, 12, '0', STR_PAD_LEFT),
                'name' => "Follower {$userId}",
                'email' => "follower{$userId}@load.exaearn.test",
                'password' => 'not-used',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $relationships[] = [
                'relationship_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'follower_id' => $userId,
                'trader_id' => $trader->id,
                'amount_allocated' => '1000',
                'copy_available' => '1000',
                'copy_locked' => '0',
                'copy_pnl' => '0',
                'risk_level' => 'medium',
                'product_scope' => 'futures',
                'copy_mode' => 'fixed_amount',
                'fixed_amount_per_trade' => '10',
                'max_amount_per_trade' => '10',
                'max_leverage' => 2,
                'margin_preference' => 'isolated',
                'high_water_mark' => '1000',
                'status' => 'active',
                'metadata' => json_encode(['load_test' => true], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($users, 1000) as $chunk) {
            DB::table('users')->insert($chunk);
        }
        foreach (array_chunk($relationships, 1000) as $chunk) {
            DB::table('copy_relationships')->insert($chunk);
        }

        unset($users, $relationships);
        gc_collect_cycles();
    }
}
