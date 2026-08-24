<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FuturesMarket;
use App\Models\FuturesOrder;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalRole;
use App\Models\InstitutionalSubaccount;
use App\Models\InstitutionalTransferRequest;
use App\Models\Market;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotHedge;
use App\Models\MarketMakerBotIncident;
use App\Models\MarketMakerBotLoadRun;
use App\Models\MarketMakerBotOrder;
use App\Models\MarketMakerBotQuoteCycle;
use App\Models\MarketMakerBotRebalance;
use App\Models\MarketMakerBotStrategyVersion;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\SpotOrderBookSnapshot;
use App\Models\User;
use App\Services\InstitutionalService;
use App\Services\LedgerService;
use App\Services\MarketMakerBotLoadTestService;
use App\Services\MarketMakerBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase15EMarketMakerBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false, 'trading.engine.mode' => 'legacy']);
    }

    public function test_mm_bot_lifecycle_shadow_live_orders_worker_lock_realtime_and_invariants(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin();
        [$institution, $subaccount, $profile] = $this->marketMakerInstitution($owner);
        $market = $this->market();
        $this->book($market);
        $this->fundUserTrading($owner, 'USDT', '1000000');
        $this->fundUserTrading($owner, 'BTC', '20');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $subaccount, 'USDT', '1000000', 'Seed MM subaccount quote inventory.');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $subaccount, 'BTC', '20', 'Seed MM subaccount base inventory.');

        $strategy = $this->actingAs($owner)->postJson('/api/institutional/market-making/bots/strategies', [
            'market_maker_id' => $profile->id,
            'name' => 'BTC Two-Sided Strategy',
            'strategy_type' => 'TWO_SIDED_MARKET_MAKING',
            'supported_markets' => ['BTC/USDT'],
            'parameters' => ['quote_size' => '0.1', 'base_spread_bps' => '20'],
        ])->assertCreated()->json('data');
        $this->assertSame('DRAFT', $strategy['status']);
        $this->assertSame(1, MarketMakerBotStrategyVersion::query()->where('strategy_id', $strategy['id'])->count());

        $bot = $this->actingAs($owner)->postJson('/api/institutional/market-making/bots', [
            'market_maker_id' => $profile->id,
            'strategy_id' => $strategy['id'],
            'name' => 'BTCUSDT Primary Bot',
            'market_symbol' => 'BTC/USDT',
            'product_type' => 'SPOT',
            'configuration' => ['quote_size' => '0.1', 'levels' => 2, 'base_spread_bps' => '20', 'quote_ttl_seconds' => 30],
            'risk_limits' => ['max_market_data_age_seconds' => 120],
        ])->assertCreated()->json('data');
        $this->assertSame('DRAFT', $bot['status']);

        $shadow = $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/shadow", [
            'idempotency_key' => 'shadow-cycle-1',
        ])->assertCreated()->json('data');
        $this->assertSame('SHADOW_RECORDED', $shadow['status']);
        $this->assertCount(4, $shadow['quote_plan']);
        $this->assertSame(0, MarketMakerBotOrder::query()->count());
        $this->assertSame(0, Order::query()->count());

        $duplicateShadow = $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/shadow", [
            'idempotency_key' => 'shadow-cycle-1',
        ])->assertCreated()->json('data');
        $this->assertSame($shadow['cycle_uuid'], $duplicateShadow['cycle_uuid']);

        $storedBot = MarketMakerBot::query()->where('bot_uuid', $bot['bot_uuid'])->firstOrFail();
        $this->assertTrue(app(MarketMakerBotService::class)->acquireLease($storedBot, 'worker-a', 60));
        $this->assertFalse(app(MarketMakerBotService::class)->acquireLease($storedBot->fresh(), 'worker-b', 60));

        $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/approve", [
            'reason' => 'Shadow cycle and capital readiness reviewed.',
        ])->assertOk();
        $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/start", [
            'reason' => 'Begin limited production quoting.',
        ])->assertOk();

        $live = $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/live-cycle", [
            'idempotency_key' => 'live-cycle-1',
        ])->assertCreated()->json('data');
        $this->assertSame('SUBMITTED', $live['status']);
        $this->assertSame(4, MarketMakerBotOrder::query()->where('quote_cycle_id', $live['id'])->where('status', 'SUBMITTED')->count());
        $this->assertSame(4, Order::query()->count());
        $this->assertSame(0, MarketMakerBotQuoteCycle::query()->where('idempotency_key', 'fake-fill')->count());

        $replay = $this->actingAs($owner)->getJson('/api/institutional/realtime/replay?stream='.urlencode("institution.{$institution->id}.mm_bot").'&after_sequence=0')
            ->assertOk()
            ->json('data');
        $this->assertGreaterThanOrEqual(4, count($replay));

        $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/reduce-only", [
            'reason' => 'Risk reduction drill.',
        ])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/live-cycle", [
            'idempotency_key' => 'blocked-reduce-only-cycle',
        ])->assertStatus(422);
        $this->assertSame(1, MarketMakerBotIncident::query()->where('category', 'BOT_RISK_BLOCK')->count());

        $snapshot = app(MarketMakerBotService::class)->snapshotPerformance($storedBot->fresh());
        $this->assertTrue((bool) $snapshot->metadata['pnl_source'] === true || $snapshot->metadata['pnl_source'] === 'real_fills_only');
    }

    public function test_mm_bot_stale_market_data_and_unapproved_live_mode_fail_closed(): void
    {
        $owner = User::factory()->create();
        [$institution, $subaccount, $profile] = $this->marketMakerInstitution($owner);
        $market = $this->market();
        $snapshot = SpotOrderBookSnapshot::query()->create([
            'snapshot_id' => (string) Str::uuid(),
            'market_id' => $market->id,
            'market_symbol' => 'BTC/USDT',
            'last_sequence' => 1,
            'bids' => [['price' => '49900', 'quantity' => '2']],
            'asks' => [['price' => '50100', 'quantity' => '2']],
            'open_orders' => [],
            'checksum' => 'stale',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        $snapshot->forceFill(['created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)])->save();
        app(InstitutionalService::class)->adminCreditSubaccount($this->admin('stale-admin@example.com'), $subaccount, 'USDT', '1000000', 'Seed quote inventory.');
        app(InstitutionalService::class)->adminCreditSubaccount($this->admin('stale-admin-2@example.com'), $subaccount, 'BTC', '20', 'Seed base inventory.');

        $strategy = $this->actingAs($owner)->postJson('/api/institutional/market-making/bots/strategies', [
            'market_maker_id' => $profile->id,
            'name' => 'Stale Strategy',
            'supported_markets' => ['BTC/USDT'],
        ])->assertCreated()->json('data');
        $bot = $this->actingAs($owner)->postJson('/api/institutional/market-making/bots', [
            'market_maker_id' => $profile->id,
            'strategy_id' => $strategy['id'],
            'name' => 'Stale Guard Bot',
            'market_symbol' => 'BTC/USDT',
            'configuration' => ['quote_size' => '0.1'],
            'risk_limits' => ['max_market_data_age_seconds' => 1],
        ])->assertCreated()->json('data');

        $this->actingAs($this->admin('live-admin@example.com'))->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/live-cycle", [
            'idempotency_key' => 'unapproved-live',
        ])->assertStatus(422);

        $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/shadow", [
            'idempotency_key' => 'stale-shadow',
        ])->assertStatus(422);
        $this->assertSame(1, MarketMakerBotIncident::query()->where('category', 'BOT_RISK_BLOCK')->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_futures_mm_hedging_cancel_replace_rebalance_shock_and_load_gates(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin('phase15e-final-admin@example.com');
        [$institution, $subaccount, $profile] = $this->marketMakerInstitution($owner);
        $treasury = InstitutionalSubaccount::query()->create([
            'subaccount_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'name' => 'Institution Treasury',
            'type' => 'TREASURY',
            'status' => 'ACTIVE',
            'risk_mode' => 'ISOLATED',
            'product_flags' => ['TREASURY' => true],
        ]);
        $market = $this->market();
        $this->book($market);
        $this->futuresMarket();
        $this->fundUserAccount($owner, 'futures', 'USDT', '1000000');
        $this->fundUserTrading($owner, 'USDT', '1000000');
        $this->fundUserTrading($owner, 'BTC', '20');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $subaccount, 'USDT', '1000000', 'Seed MM quote inventory.');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $subaccount, 'BTC', '20', 'Seed MM base inventory.');

        $strategy = $this->actingAs($owner)->postJson('/api/institutional/market-making/bots/strategies', [
            'market_maker_id' => $profile->id,
            'name' => 'BTC Futures Strategy',
            'strategy_type' => 'TWO_SIDED_MARKET_MAKING',
            'supported_markets' => ['BTC/USDT'],
        ])->assertCreated()->json('data');

        $bot = $this->actingAs($owner)->postJson('/api/institutional/market-making/bots', [
            'market_maker_id' => $profile->id,
            'strategy_id' => $strategy['id'],
            'name' => 'BTC Futures MM Bot',
            'market_symbol' => 'BTC/USDT',
            'product_type' => 'FUTURES',
            'configuration' => [
                'quote_size' => '0.1',
                'levels' => 1,
                'base_spread_bps' => '20',
                'quote_ttl_seconds' => 30,
                'futures_market_symbol' => 'BTCUSDTPERP',
                'futures_leverage' => 1,
                'hedge_mode' => 'AUTOMATED_WITH_LIMITS',
                'hedge_ratio' => '0.5',
            ],
            'risk_limits' => [
                'max_market_data_age_seconds' => 120,
                'max_mark_index_divergence_bps' => '100',
                'max_futures_leverage' => 2,
                'max_hedge_ratio' => '1',
                'max_hedge_notional' => '500000',
                'market_shock_bps' => '100',
            ],
        ])->assertCreated()->json('data');

        $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/approve", [
            'reason' => 'Final Phase 15E futures MM approval.',
        ])->assertOk();
        $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/start", [
            'reason' => 'Start futures MM limited production.',
        ])->assertOk();

        $firstCycle = $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/live-cycle", [
            'idempotency_key' => 'futures-mm-cycle-1',
        ])->assertCreated()->json('data');
        $this->assertSame('SUBMITTED', $firstCycle['status']);
        $this->assertSame(2, FuturesOrder::query()->where('source', 'market_maker_bot')->count());
        $this->assertSame(2, MarketMakerBotOrder::query()->whereNotNull('futures_order_id')->where('status', 'SUBMITTED')->count());

        $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/live-cycle", [
            'idempotency_key' => 'futures-mm-cycle-2',
        ])->assertCreated();
        $this->assertSame(2, FuturesOrder::query()->where('source', 'market_maker_bot')->count(), 'Unchanged futures quotes should be kept, not recreated.');

        $hedge = $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/hedge", [
            'idempotency_key' => 'hedge-1',
        ])->assertCreated()->json('data');
        $this->assertSame('SUBMITTED', $hedge['status']);
        $this->assertSame(1, MarketMakerBotHedge::query()->where('idempotency_key', 'hedge-1')->count());
        $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/hedge", [
            'idempotency_key' => 'hedge-1',
        ])->assertCreated();
        $this->assertSame(1, MarketMakerBotHedge::query()->where('idempotency_key', 'hedge-1')->count());

        $rebalance = $this->actingAs($owner)->postJson("/api/institutional/market-making/bots/{$bot['bot_uuid']}/rebalance", [
            'asset' => 'USDT',
            'amount' => '100',
            'destination_subaccount_id' => $treasury->id,
            'mode' => 'AUTOMATED_WITH_LIMITS',
            'idempotency_key' => 'rebalance-1',
            'approval_threshold' => '1000',
        ])->assertCreated()->json('data');
        $this->assertSame('COMPLETED', $rebalance['status']);
        $this->assertSame(1, MarketMakerBotRebalance::query()->where('idempotency_key', 'rebalance-1')->count());
        $this->assertSame(1, InstitutionalTransferRequest::query()->where('idempotency_key', 'MM-BOT-REBALANCE-rebalance-1')->count());

        $primaryBot = MarketMakerBot::query()->where('bot_uuid', $bot['bot_uuid'])->firstOrFail();
        for ($i = 2; $i <= 10; $i++) {
            MarketMakerBot::query()->create(array_merge($primaryBot->replicate()->toArray(), [
                'bot_uuid' => (string) Str::uuid(),
                'name' => "BTC Futures MM Bot {$i}",
                'status' => 'ACTIVE',
                'safety_state' => 'NORMAL',
                'worker_id' => null,
                'worker_lease_expires_at' => null,
            ]));
        }
        $run = app(MarketMakerBotLoadTestService::class)->run(10, 3);
        $this->assertSame('PASS', $run->status);
        $this->assertSame(30, $run->metrics['decisions']);
        $this->assertSame(1, MarketMakerBotLoadRun::query()->count());

        $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/mass-cancel", [
            'reason' => 'Emergency mass cancel drill.',
        ])->assertOk();
        $this->assertSame(2, MarketMakerBotOrder::query()->whereNotNull('futures_order_id')->where('status', 'CANCELLED')->count());
        $this->assertSame(2, FuturesOrder::query()->where('source', 'market_maker_bot')->where('status', 'cancelled')->count());

        $this->bookWithPrice($market, '39900', '40100');
        $this->actingAs($admin)->postJson("/api/admin/v1/market-makers/bots/{$bot['bot_uuid']}/shock-check")
            ->assertOk()
            ->assertJsonPath('data.status', 'SHOCK_DETECTED');
        $this->assertSame(1, MarketMakerBotIncident::query()->where('category', 'MARKET_SHOCK')->count());
    }

    private function market(): Market
    {
        return Market::query()->create([
            'symbol' => 'BTC/USDT',
            'base_currency' => 'BTC',
            'quote_currency' => 'USDT',
            'status' => 'active',
            'engine_mode' => 'legacy',
            'last_price' => '50000',
            'price_precision' => '0.01000000',
            'tick_size' => '0.010000000000000000',
            'quantity_step' => '0.000100000000000000',
            'min_order_size' => '0.00010000',
            'max_order_size' => '100.00000000',
            'min_notional' => '10.000000000000000000',
            'maker_fee' => '0.00100000',
            'taker_fee' => '0.00200000',
        ]);
    }

    private function book(Market $market): void
    {
        SpotOrderBookSnapshot::query()->create([
            'snapshot_id' => (string) Str::uuid(),
            'market_id' => $market->id,
            'market_symbol' => 'BTC/USDT',
            'last_sequence' => 10,
            'bids' => [['price' => '49900', 'quantity' => '2']],
            'asks' => [['price' => '50100', 'quantity' => '2']],
            'open_orders' => [],
            'checksum' => 'fresh',
        ]);
    }

    private function bookWithPrice(Market $market, string $bid, string $ask): void
    {
        SpotOrderBookSnapshot::query()->create([
            'snapshot_id' => (string) Str::uuid(),
            'market_id' => $market->id,
            'market_symbol' => 'BTC/USDT',
            'last_sequence' => random_int(100, 999),
            'bids' => [['price' => $bid, 'quantity' => '2']],
            'asks' => [['price' => $ask, 'quantity' => '2']],
            'open_orders' => [],
            'checksum' => 'shock',
        ]);
    }

    private function futuresMarket(): FuturesMarket
    {
        return FuturesMarket::query()->create([
            'symbol' => 'BTCUSDTPERP',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'settlement_asset' => 'USDT',
            'contract_type' => 'PERPETUAL',
            'min_leverage' => 1,
            'max_leverage' => 5,
            'maintenance_margin_rate' => '0.00500000',
            'last_price' => '50000',
            'tick_size' => '0.01000000',
            'quantity_step' => '0.00010000',
            'min_quantity' => '0.00010000',
            'max_quantity' => '100',
            'min_notional' => '10',
            'max_notional' => '10000000',
            'index_price' => '50000',
            'mark_price' => '50000',
            'funding_rate' => '0.0001000000',
            'next_funding_time' => now()->addHours(8),
            'status' => 'active',
            'engine_mode' => 'legacy',
            'risk_tiers' => [],
            'price_band_bps' => '500',
        ]);
    }

    private function marketMakerInstitution(User $owner): array
    {
        $institution = InstitutionalAccount::query()->create([
            'institution_uuid' => (string) Str::uuid(),
            'master_user_id' => $owner->id,
            'legal_name' => 'MM Bot Desk Ltd',
            'country_of_incorporation' => 'NG',
            'business_type' => 'INSTITUTION',
            'status' => 'ACTIVE',
            'kyb_status' => 'APPROVED',
            'compliance_status' => 'APPROVED',
        ]);
        $role = InstitutionalRole::query()->create([
            'institution_id' => $institution->id,
            'name' => 'OWNER',
            'role_type' => 'OWNER',
            'permissions' => InstitutionalService::OWNER_PERMISSIONS,
            'system_template' => true,
        ]);
        InstitutionalMembership::query()->create([
            'membership_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'user_id' => $owner->id,
            'role_id' => $role->id,
            'status' => 'ACTIVE',
            'accepted_at' => now(),
        ]);
        $subaccount = InstitutionalSubaccount::query()->create([
            'subaccount_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'name' => 'MM Bot Subaccount',
            'type' => 'MARKET_MAKER',
            'status' => 'ACTIVE',
            'risk_mode' => 'ISOLATED',
            'product_flags' => ['SPOT' => true, 'MM_BOT' => true],
        ]);
        $profile = MarketMakerProfile::query()->create([
            'profile_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'subaccount_id' => $subaccount->id,
            'status' => 'ACTIVE',
            'provider_type' => 'INSTITUTIONAL_MARKET_MAKER',
            'rate_profile' => 'MARKET_MAKER_STANDARD',
            'safety_mode' => 'NORMAL',
            'approved_markets' => ['BTC/USDT'],
            'limits' => ['max_order_rate_per_second' => 50, 'max_cancel_rate_per_second' => 100],
        ]);
        MarketMakerMarketAssignment::query()->create([
            'assignment_uuid' => (string) Str::uuid(),
            'market_maker_id' => $profile->id,
            'market_symbol' => 'BTC/USDT',
            'status' => 'ACTIVE',
            'minimum_depth' => '1',
            'maximum_spread_bps' => '100',
            'minimum_quote_presence' => '95',
            'target_quote_size' => '0.1',
            'maximum_inventory' => '1000000',
        ]);

        return [$institution, $subaccount, $profile];
    }

    private function fundUserTrading(User $user, string $asset, string $amount): void
    {
        $this->fundUserAccount($user, 'unified_trading', $asset, $amount);
    }

    private function fundUserAccount(User $user, string $accountType, string $asset, string $amount): void
    {
        $ledger = app(LedgerService::class);
        $source = $ledger->getOrCreateAccount(null, 'mm_bot_test_seed', $asset);
        $destination = $ledger->getOrCreateAccount($user->id, $accountType, $asset);
        $ledger->postDoubleEntry('MM-BOT-SEED-'.$user->id.'-'.$accountType.'-'.$asset.'-'.Str::uuid(), 'Seed MM bot live order account.', [
            ['account_id' => $source->id, 'amount' => bcmul($amount, '-1', 18), 'asset' => $asset],
            ['account_id' => $destination->id, 'amount' => $amount, 'asset' => $asset],
        ], 'mm_bot_test_seed', ['source_service' => 'test']);
    }

    private function admin(string $email = 'mm-bot-admin@example.com'): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'MM Bot Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
