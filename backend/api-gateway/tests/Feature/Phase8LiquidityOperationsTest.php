<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Admin;
use App\Models\BestExecutionAudit;
use App\Models\ExternalFill;
use App\Models\ExternalVenueAccount;
use App\Models\ExternalVenueBalance;
use App\Models\LiquidityReservation;
use App\Models\LiquidityRoutePlan;
use App\Models\LiquiditySource;
use App\Models\Market;
use App\Models\MarketMakerAccount;
use App\Models\MarketMakerQuote;
use App\Models\TreasuryLiquidityBucket;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\Liquidity\LiquidityLoadProbeService;
use App\Services\Liquidity\LiquidityOperationalReadinessService;
use App\Services\Liquidity\LiquidityReconciliationService;
use App\Services\Liquidity\LiquidityReservationService;
use App\Services\Liquidity\LiquiditySourceRegistry;
use App\Services\Liquidity\MarketMakingEngineService;
use App\Services\Liquidity\NetExposureService;
use App\Services\Liquidity\SmartOrderRouter;
use App\Services\Liquidity\TreasuryInventoryService;
use App\Services\Liquidity\TreasuryRebalancingService;
use App\Services\Liquidity\WithdrawalLiquidityReserveService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class Phase8LiquidityOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trading.engine.mode', 'new');
        Config::set('liquidity.external_venues.binance.enabled', false);
        Config::set('liquidity.market_making.enabled', true);
        Config::set('trading_operations.default_max_order_notional', '10000000');
        Config::set('trading_operations.max_order_price_deviation_bps', 5000);

        Http::fake([
            'api.binance.com/api/v3/depth*' => Http::response([
                'bids' => [['99900.00', '2']],
                'asks' => [['100100.00', '2']],
            ]),
        ]);

        Market::query()->create([
            'symbol' => 'BTC/USDT',
            'base_currency' => 'BTC',
            'quote_currency' => 'USDT',
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

    public function test_liquidity_source_registry_marks_binance_not_configured_not_live(): void
    {
        app(LiquiditySourceRegistry::class)->syncConfiguredSources();

        $source = LiquiditySource::query()->where('code', 'BINANCE')->firstOrFail();

        $this->assertSame('UNCONFIGURED', $source->state);
        $this->assertFalse((bool) $source->capabilities['order_placement']);
    }

    public function test_sor_uses_internal_executable_depth_and_records_best_execution_audit(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '100000');
        app(TradeService::class)->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.20', '100000');

        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $plan = app(SmartOrderRouter::class)->plan($market, $buyer->id, 'buy', '0.10', [
            'parent_reference' => 'phase8-sor-internal',
            'idempotency_key' => 'phase8-sor-internal',
        ]);

        $this->assertSame('ROUTE_PLANNED', $plan->status);
        $this->assertSame('EXAEARN_INTERNAL', $plan->plan[0]['source']);
        $this->assertSame(1, BestExecutionAudit::query()->where('parent_reference', 'phase8-sor-internal')->count());
    }

    public function test_sor_rejects_reference_only_external_depth_as_non_executable(): void
    {
        $buyer = $this->fundTrading('USDT', '100000');
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('INSUFFICIENT_EXECUTABLE_LIQUIDITY');

        app(SmartOrderRouter::class)->plan($market, $buyer->id, 'buy', '0.10', [
            'parent_reference' => 'phase8-reference-reject',
            'idempotency_key' => 'phase8-reference-reject',
        ]);
    }

    public function test_liquidity_reservation_is_idempotent_and_blocks_over_reservation(): void
    {
        app(TreasuryInventoryService::class)->allocateBucket('USDT', 'EXTERNAL_ROUTING', '1000');

        $first = app(LiquidityReservationService::class)->reserve('TREASURY', 'EXTERNAL_ROUTING', 'USDT', '600', 'SOR', 'route', 'r1', 'idem-1');
        $duplicate = app(LiquidityReservationService::class)->reserve('TREASURY', 'EXTERNAL_ROUTING', 'USDT', '600', 'SOR', 'route', 'r1', 'idem-1');

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame(0, FinancialDecimal::compare('600.000000000000000000', (string) TreasuryLiquidityBucket::query()->where('asset', 'USDT')->where('bucket', 'EXTERNAL_ROUTING')->value('reserved_amount')));

        $this->expectException(RuntimeException::class);
        app(LiquidityReservationService::class)->reserve('TREASURY', 'EXTERNAL_ROUTING', 'USDT', '500', 'SOR', 'route', 'r2', 'idem-2');
    }

    public function test_duplicate_external_fill_is_rejected_by_unique_venue_trade_id(): void
    {
        $account = ExternalVenueAccount::query()->create([
            'liquidity_source_id' => LiquiditySource::query()->create([
                'source_id' => fake()->uuid(),
                'code' => 'VENUE1',
                'name' => 'Venue 1',
                'type' => 'EXTERNAL_VENUE',
                'state' => 'TESTING',
            ])->id,
            'venue' => 'VENUE1',
            'account_reference' => 'test',
            'state' => 'TESTING',
        ]);
        $order = \App\Models\ExternalOrder::query()->create([
            'external_execution_id' => fake()->uuid(),
            'external_venue_account_id' => $account->id,
            'venue' => 'VENUE1',
            'client_order_id' => 'client-1',
            'market_symbol' => 'BTC/USDT',
            'venue_symbol' => 'BTCUSDT',
            'side' => 'buy',
            'type' => 'market',
            'quantity' => '0.1',
            'status' => 'SUBMITTED',
        ]);

        ExternalFill::query()->create([
            'external_order_id' => $order->id,
            'venue' => 'VENUE1',
            'venue_trade_id' => 'trade-1',
            'market_symbol' => 'BTC/USDT',
            'price' => '100000',
            'quantity' => '0.1',
            'quote_quantity' => '10000',
            'filled_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ExternalFill::query()->create([
            'external_order_id' => $order->id,
            'venue' => 'VENUE1',
            'venue_trade_id' => 'trade-1',
            'market_symbol' => 'BTC/USDT',
            'price' => '100000',
            'quantity' => '0.1',
            'quote_quantity' => '10000',
            'filled_at' => now(),
        ]);
    }

    public function test_withdrawal_reserve_blocks_market_making_inventory_consumption(): void
    {
        app(TreasuryInventoryService::class)->allocateBucket('BTC', 'WITHDRAWAL_RESERVE', '0.50');
        Config::set('liquidity.treasury.default_minimum_reserve', '0.40');
        $reserve = app(WithdrawalLiquidityReserveService::class)->calculate('BTC');
        $this->assertSame('BELOW_TARGET', $reserve->status);

        $maker = MarketMakerAccount::query()->create([
            'market_maker_id' => fake()->uuid(),
            'name' => 'ExaEarn Treasury MM',
            'account_type' => 'TREASURY',
            'status' => 'ACTIVE',
            'limits' => ['spread_bps' => '20'],
        ]);
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WITHDRAWAL_RESERVE_BREACH');
        app(MarketMakingEngineService::class)->quote($market, $maker, '100000', '0.20');
    }

    public function test_net_exposure_detects_under_backed_asset(): void
    {
        $user = User::factory()->create();
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '1000',
            'status' => 'active',
        ]);
        Account::query()->create([
            'owner_type' => 'system',
            'owner_id' => null,
            'user_id' => null,
            'account_type' => 'treasury',
            'asset' => 'USDT',
            'balance' => '500',
            'status' => 'active',
        ]);

        $snapshot = app(NetExposureService::class)->calculate('USDT');

        $this->assertSame('UNDER_BACKED', $snapshot->status);
        $this->assertSame(0, FinancialDecimal::compare('0.500000000000000000', (string) $snapshot->coverage_ratio));
    }

    public function test_rebalancing_recommends_action_when_withdrawal_reserve_is_low(): void
    {
        Config::set('liquidity.treasury.default_minimum_reserve', '10');
        app(TreasuryInventoryService::class)->allocateBucket('USDT', 'WITHDRAWAL_RESERVE', '2');

        $run = app(TreasuryRebalancingService::class)->evaluate('USDT');

        $this->assertSame('ACTION_REQUIRED', $run->status);
        $this->assertSame('TREASURY_TO_HOT', $run->actions[0]['mode']);
    }

    public function test_liquidity_reconciliation_detects_bucket_over_reservation(): void
    {
        TreasuryLiquidityBucket::query()->create([
            'bucket_id' => fake()->uuid(),
            'asset' => 'USDT',
            'bucket' => 'EXTERNAL_ROUTING',
            'allocated_amount' => '100',
            'reserved_amount' => '200',
            'status' => 'ACTIVE',
        ]);

        $run = app(LiquidityReconciliationService::class)->run();

        $this->assertSame('FAIL', $run->status);
        $this->assertTrue($run->differences()->where('code', 'BUCKET_RESERVED_EXCEEDS_ALLOCATED')->exists());
    }

    public function test_admin_liquidity_readiness_and_load_probe_are_available(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/api/admin/v1/liquidity/readiness')
            ->assertOk()
            ->assertJsonPath('data.overall_status', 'READY')
            ->assertJsonPath('data.external_production_venues', 'NOT_CONFIGURED');

        $run = app(LiquidityLoadProbeService::class)->run(2);
        $this->assertSame('PASS', $run->status);
    }

    private function fundTrading(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->update(['balance' => '1000000']);
        $ledger->fiatDeposit($user->id, $amount, $asset, 'phase8-seed-' . $asset . '-' . $user->id);
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, 'phase8-trading-' . $asset . '-' . $user->id);

        return $user;
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Phase 8 Admin',
            'email' => 'phase8-admin-' . uniqid() . '@example.test',
            'password' => 'secret',
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
