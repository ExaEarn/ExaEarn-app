<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CollateralConfiguration;
use App\Models\FinancialReconciliationRun;
use App\Models\InsuranceFundTransaction;
use App\Models\MarginLendingPool;
use App\Models\Market;
use App\Models\TradingCircuitBreaker;
use App\Models\TradingIncident;
use App\Models\TradingLoadRun;
use App\Services\AccountEquityService;
use App\Services\CircuitBreakerService;
use App\Services\FinancialDecimal;
use App\Services\FinancialReconciliationService;
use App\Services\InsuranceFundService;
use App\Services\LedgerService;
use App\Services\NegativeEquityProtectionService;
use App\Services\PriceProtectionService;
use App\Services\TradeService;
use App\Services\TradingLoadProbeService;
use App\Services\TradingRiskEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class Phase7TradingOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trading.engine.mode', 'new');
        Config::set('trading_operations.max_order_price_deviation_bps', 500);
        Config::set('trading_operations.price_feed_max_age_ms', 60000);
        Config::set('trading_operations.default_max_order_notional', '1000000');
        Config::set('margin.reference_prices.BTC', '50000');

        Market::query()->updateOrCreate(['symbol' => 'BTC/USDT'], [
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

    public function test_unified_risk_engine_rejects_orders_when_market_breaker_is_paused(): void
    {
        $user = $this->fundTrading('USDT', '1000');
        app(CircuitBreakerService::class)->transition('MARKET', 'BTC/USDT', TradingCircuitBreaker::PAUSED, 'Phase 7 test pause', null, 'TEST_PAUSE');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MARKET_PAUSED');

        app(TradeService::class)->placeOrder($user->id, 'BTC/USDT', 'buy', 'limit', '0.01', '50000', [
            'client_order_id' => 'phase7-paused-order',
        ]);
    }

    public function test_price_protection_rejects_extreme_limit_price(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        app(PriceProtectionService::class)->quality('BTC/USDT', '50000', now()->toISOString());

        $decision = app(PriceProtectionService::class)->validateOrderPrice($market, '1000000', 'buy', 'limit');

        $this->assertFalse($decision['allowed']);
        $this->assertSame('PRICE_DEVIATION_LIMIT', $decision['reason_code']);
    }

    public function test_admin_can_pause_resume_market_and_global_kill_switch_blocks_readiness(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/v1/operations/markets/BTCUSDT/pause', ['reason' => 'testing controlled pause'])
            ->assertOk()
            ->assertJsonPath('data.state', TradingCircuitBreaker::PAUSED);

        $this->assertSame('halted', Market::query()->where('symbol', 'BTC/USDT')->value('trading_status'));

        $this->actingAs($admin)
            ->postJson('/api/admin/v1/operations/markets/BTCUSDT/resume', ['reason' => 'testing resume'])
            ->assertOk()
            ->assertJsonPath('data.state', TradingCircuitBreaker::NORMAL);

        $this->actingAs($admin)
            ->postJson('/api/admin/v1/operations/kill-switch', ['reason' => 'testing global emergency stop'])
            ->assertOk()
            ->assertJsonPath('data.state', TradingCircuitBreaker::EMERGENCY_STOP);

        $this->actingAs($admin)
            ->getJson('/api/admin/v1/operations/readiness')
            ->assertOk()
            ->assertJsonPath('data.overall', 'NOT_READY')
            ->assertJsonFragment(['EMERGENCY_STOP_ACTIVE']);
    }

    public function test_financial_reconciliation_detects_lending_pool_deficit(): void
    {
        MarginLendingPool::query()->create([
            'asset' => 'USDT',
            'total_liquidity' => '100.000000000000000000',
            'available_liquidity' => '-1.000000000000000000',
            'borrowed_liquidity' => '101.000000000000000000',
            'reserve_balance' => '0',
            'status' => 'ENABLED',
        ]);

        $run = app(FinancialReconciliationService::class)->run();

        $this->assertSame('CRITICAL', $run->status);
        $this->assertTrue($run->differences()->where('code', 'LENDING_POOL_DEFICIT')->exists());
    }

    public function test_collateral_config_is_versioned_and_account_equity_uses_haircut(): void
    {
        $admin = $this->admin();
        $user = $this->fundTrading('USDT', '1000');

        $this->actingAs($admin)
            ->putJson('/api/admin/v1/operations/collateral/USDT', [
                'collateral_factor' => '0.50000000',
                'reason' => 'testing haircut governance',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $equity = app(AccountEquityService::class)->userEquity($user->id);

        $this->assertSame(0, FinancialDecimal::compare('0.50000000', app(AccountEquityService::class)->collateralFactor('USDT')));
        $this->assertSame(0, FinancialDecimal::compare('500.000000000000000000', (string) $equity['collateral_value_usdt']));
        $this->assertDatabaseHas('collateral_configuration_versions', ['version' => 1]);
    }

    public function test_insurance_fund_transactions_are_idempotent(): void
    {
        $fund = app(InsuranceFundService::class)->credit('futures', 'USDT', '100', 'phase7-insurance-credit');
        $duplicate = app(InsuranceFundService::class)->credit('futures', 'USDT', '100', 'phase7-insurance-credit');
        app(InsuranceFundService::class)->useFund('futures', 'USDT', '40', 'phase7-insurance-use');

        $this->assertSame($fund->id, $duplicate->id);
        $this->assertSame(2, InsuranceFundTransaction::query()->count());
        $this->assertSame(0, FinancialDecimal::compare('60.000000000000000000', (string) $fund->fresh()->balance));
    }

    public function test_negative_equity_creates_incident_and_bad_debt_record(): void
    {
        $user = $this->fundTrading('USDT', '1');
        \App\Models\MarginAccount::query()->create([
            'account_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'mode' => 'CROSS',
            'status' => 'ACTIVE',
        ]);
        $account = \App\Models\MarginAccount::query()->where('user_id', $user->id)->firstOrFail();
        \App\Models\MarginLoan::query()->create([
            'loan_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'margin_account_id' => $account->id,
            'user_id' => $user->id,
            'asset' => 'USDT',
            'principal' => '100.000000000000000000',
            'accrued_interest' => '0',
            'interest_rate' => '0.10000000',
            'opened_at' => now(),
            'last_accrual_at' => now(),
            'status' => 'ACTIVE',
            'idempotency_key' => 'phase7-negative-equity-loan',
        ]);

        $result = app(NegativeEquityProtectionService::class)->checkUser($user->id);

        $this->assertSame('CRITICAL', $result['status']);
        $this->assertSame(1, TradingIncident::query()->where('type', 'NEGATIVE_EQUITY')->count());
        $this->assertDatabaseHas('margin_bad_debts', ['user_id' => $user->id, 'asset' => 'USDT', 'status' => 'OPEN']);
    }

    public function test_load_probe_persists_latency_metrics(): void
    {
        $run = app(TradingLoadProbeService::class)->run('phase7-test', 5);

        $this->assertSame('PASS', $run->status);
        $this->assertSame(5, $run->operations);
        $this->assertSame(1, TradingLoadRun::query()->where('run_id', $run->run_id)->count());
    }

    private function fundTrading(string $asset, string $amount): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['two_factor_enabled' => false]);
        app(LedgerService::class)->getOrCreateAccount(null, 'treasury', $asset)->update(['balance' => '1000000']);
        app(LedgerService::class)->fiatDeposit($user->id, $amount, $asset, 'phase7-seed-' . $asset . '-' . $user->id);
        app(LedgerService::class)->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, 'phase7-trading-' . $asset . '-' . $user->id);

        return $user;
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Phase 7 Admin',
            'email' => 'phase7-admin-' . uniqid() . '@example.test',
            'password' => 'secret',
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
