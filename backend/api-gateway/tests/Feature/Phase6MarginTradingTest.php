<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Admin;
use App\Models\MarginAssetConfig;
use App\Models\MarginBadDebt;
use App\Models\MarginLendingPool;
use App\Models\MarginLoadRun;
use App\Models\MarginLoan;
use App\Models\MarginOrder;
use App\Models\MarginRealtimeEvent;
use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\MarginAccountService;
use App\Services\MarginBorrowService;
use App\Services\MarginHealthService;
use App\Services\MarginInterestAccrualService;
use App\Services\MarginLiquidityService;
use App\Services\MarginLiquidationService;
use App\Services\MarginOrderService;
use App\Services\MarginOperationalReadinessService;
use App\Services\MarginReconciliationService;
use App\Services\MarginRepayService;
use App\Services\MarginTransferService;
use App\Services\RealtimeStreamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Phase6MarginTradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::shouldReceive('publish')->zeroOrMoreTimes();
        Config::set('margin.mode', 'internal');
        Config::set('margin.reference_prices.USDT', '1');
        Config::set('margin.reference_prices.BTC', '50000');
        Config::set('margin.health.initial_borrow_min', '1.250000000000000000');
        Config::set('margin.health.borrow_disabled', '1.050000000000000000');
        Config::set('margin.health.liquidation', '1.000000000000000000');
        Config::set('trading.engine.mode', 'new');

        $this->asset('USDT', true, true, '0.90000000');
        $this->asset('BTC', true, true, '0.80000000');
        $this->market();
    }

    public function test_cross_and_isolated_margin_accounts_are_idempotent_and_separate(): void
    {
        $user = User::factory()->create();
        $accounts = app(MarginAccountService::class);

        $crossA = $accounts->getOrCreateCrossAccount($user->id);
        $crossB = $accounts->getOrCreateCrossAccount($user->id);
        $btc = $accounts->getOrCreateIsolatedAccount($user->id, 'BTCUSDT');
        $eth = $accounts->getOrCreateIsolatedAccount($user->id, 'ETH/USDT');

        $this->assertSame($crossA->id, $crossB->id);
        $this->assertSame('margin_cross', $accounts->ledgerAccountType($crossA));
        $this->assertSame('margin_isolated_btc_usdt', $accounts->ledgerAccountType($btc));
        $this->assertNotSame($btc->id, $eth->id);
    }

    public function test_borrow_uses_real_pool_liquidity_and_canonical_ledger(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '600');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6-collateral-in');

        $loan = app(MarginBorrowService::class)->borrow($account, 'USDT', '500', 'borrow-one');
        $duplicate = app(MarginBorrowService::class)->borrow($account, 'USDT', '500', 'borrow-one');

        $this->assertSame($loan->id, $duplicate->id);
        $this->assertSame('1500.000000000000000000', app(LedgerService::class)->getBalance($user->id, 'USDT', 'margin_cross'));
        $this->assertDatabaseHas('margin_lending_pools', [
            'asset' => 'USDT',
            'available_liquidity' => '100.000000000000000000',
            'borrowed_liquidity' => '500.000000000000000000',
        ]);
        $this->assertSame(2, LedgerEntry::query()->where('reference', 'margin-borrow:borrow-one')->count());
    }

    public function test_borrow_rejects_when_lending_pool_has_insufficient_liquidity(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6-collateral-small-pool');
        $this->fundPool('USDT', '100');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient margin lending liquidity.');
        app(MarginBorrowService::class)->borrow($account, 'USDT', '200', 'borrow-too-large');
    }

    public function test_repay_pays_interest_before_principal_and_restores_liquidity(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6-repay-collateral');
        $loan = app(MarginBorrowService::class)->borrow($account, 'USDT', '500', 'borrow-repay');
        $loan->forceFill(['accrued_interest' => '10.000000000000000000', 'last_accrual_at' => now()])->save();

        $repaid = app(MarginRepayService::class)->repay($loan, '110', 'repay-one');
        $duplicate = app(MarginRepayService::class)->repay($repaid, '110', 'repay-one');

        $this->assertSame($repaid->id, $duplicate->id);
        $this->assertSame('400.000000000000000000', (string) $duplicate->principal);
        $this->assertSame('0.000000000000000000', (string) $duplicate->accrued_interest);
        $this->assertSame('600.000000000000000000', (string) MarginLendingPool::query()->where('asset', 'USDT')->value('available_liquidity'));
        $this->assertSame(4, LedgerEntry::query()->where('reference', 'margin-repay:repay-one')->count());
    }

    public function test_interest_accrual_is_deterministic_and_period_idempotent(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6-interest-collateral');
        $loan = app(MarginBorrowService::class)->borrow($account, 'USDT', '500', 'borrow-interest');
        $loan->forceFill(['last_accrual_at' => now()->subDay(), 'interest_rate' => '0.10000000'])->save();
        $periodEnd = now();

        $first = app(MarginInterestAccrualService::class)->accrueLoan($loan->fresh(), $periodEnd);
        $second = app(MarginInterestAccrualService::class)->accrueLoan($loan->fresh(), $periodEnd);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $loan->accruals()->count());
        $this->assertTrue(bccomp((string) $loan->fresh()->accrued_interest, '0', 18) > 0);
    }

    public function test_collateral_factor_drives_health_and_transfer_out_guard(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6-risk-collateral');
        app(MarginBorrowService::class)->borrow($account, 'USDT', '500', 'borrow-risk');

        $health = app(MarginHealthService::class)->health($account);
        $this->assertSame('HEALTHY', $health['status']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transfer would make the margin account unsafe.');
        app(MarginTransferService::class)->transferOut($account, 'funding', 'USDT', '1000', 'phase6-risk-transfer-out');
    }

    public function test_isolated_margin_does_not_share_unrelated_collateral(): void
    {
        $user = $this->seedUserFunding('1000');
        $accounts = app(MarginAccountService::class);
        $btc = $accounts->getOrCreateIsolatedAccount($user->id, 'BTC/USDT');
        $eth = $accounts->getOrCreateIsolatedAccount($user->id, 'ETH/USDT');
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($btc, 'funding', 'USDT', '1000', 'phase6-btc-iso-collateral');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projected margin health is below the initial borrow requirement.');
        app(MarginBorrowService::class)->borrow($eth, 'USDT', '100', 'eth-iso-borrow-without-collateral');
    }

    public function test_liquidation_records_bad_debt_without_fabricating_settlement(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6-liquidation-collateral');
        $loan = app(MarginBorrowService::class)->borrow($account, 'USDT', '500', 'borrow-liquidation');
        $loan->forceFill(['principal' => '5000.000000000000000000'])->save();

        $liquidation = app(MarginLiquidationService::class)->openIfUnsafe($account);

        $this->assertNotNull($liquidation);
        $this->assertSame('LIQUIDATION_PENDING', $account->fresh()->status);
        $this->assertSame(1, MarginBadDebt::query()->count());
    }

    public function test_margin_reconciliation_detects_pool_invariants(): void
    {
        MarginLendingPool::query()->create([
            'asset' => 'USDT',
            'total_liquidity' => '100.000000000000000000',
            'available_liquidity' => '120.000000000000000000',
            'borrowed_liquidity' => '10.000000000000000000',
            'reserve_balance' => '0',
            'status' => 'ENABLED',
        ]);

        $findings = app(MarginReconciliationService::class)->run();

        $this->assertCount(1, $findings);
        $this->assertSame('HIGH', $findings[0]->severity);
    }

    public function test_authenticated_margin_overview_api_returns_real_state(): void
    {
        $user = $this->seedUserFunding('1000');
        $this->actingAs($user);

        $response = $this->getJson('/api/margin/overview');

        $response->assertOk()
            ->assertJsonPath('mode', 'internal')
            ->assertJsonStructure(['accounts', 'loans', 'pools']);
    }

    public function test_margin_realtime_events_are_published_for_account_changes(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $stream = \Mockery::mock(RealtimeStreamService::class);
        $stream->shouldReceive('publishPayload')
            ->once()
            ->with('margin_updates', \Mockery::on(fn (array $payload): bool => $payload['event'] === 'margin.account.transferred_in'
                && (int) $payload['user_id'] === $user->id
                && data_get($payload, 'data.account_uuid') === $account->account_uuid
                && data_get($payload, 'data.asset') === 'USDT'
                && data_get($payload, 'data.amount') === '100.000000000000000000'));
        $this->app->instance(RealtimeStreamService::class, $stream);

        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '100', 'phase6b-realtime-transfer');
    }

    public function test_margin_order_routes_to_spot_oms_with_margin_account_context(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6b-order-collateral');

        $marginOrder = app(MarginOrderService::class)->place($account, [
            'client_order_id' => 'margin-order-one',
            'pair' => 'BTC/USDT',
            'side' => 'buy',
            'type' => 'limit',
            'amount' => '0.01',
            'price' => '50000',
            'borrow_mode' => 'NORMAL',
        ]);
        $duplicate = app(MarginOrderService::class)->place($account, [
            'client_order_id' => 'margin-order-one',
            'pair' => 'BTC/USDT',
            'side' => 'buy',
            'type' => 'limit',
            'amount' => '0.01',
            'price' => '50000',
            'borrow_mode' => 'NORMAL',
        ]);

        $this->assertSame($marginOrder->id, $duplicate->id);
        $this->assertSame(MarginOrder::STATUS_SUBMITTED, $marginOrder->status);
        $this->assertNotNull($marginOrder->spot_order_id);
        $this->assertSame('MARGIN', data_get($marginOrder->spotOrder->metadata, 'source'));
        $this->assertSame('margin_cross', data_get($marginOrder->spotOrder->metadata, 'account_type'));
        $this->assertDatabaseHas('reservations', [
            'account_id' => app(LedgerService::class)->getOrCreateAccount($user->id, 'margin_cross', 'USDT')->id,
            'purpose' => 'spot_order',
        ]);
    }

    public function test_margin_auto_borrow_creates_debt_before_spot_order_submission(): void
    {
        $user = $this->seedUserFunding('200');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '200', 'phase6b-auto-borrow-collateral');

        $marginOrder = app(MarginOrderService::class)->place($account, [
            'client_order_id' => 'margin-auto-borrow-one',
            'pair' => 'BTC/USDT',
            'side' => 'buy',
            'type' => 'limit',
            'amount' => '0.01',
            'price' => '50000',
            'borrow_mode' => 'AUTO_BORROW',
        ]);

        $this->assertSame(MarginOrder::STATUS_SUBMITTED, $marginOrder->status);
        $this->assertSame('USDT', $marginOrder->auto_borrow_asset);
        $this->assertSame('300.000000000000000000', (string) $marginOrder->auto_borrow_amount);
        $this->assertSame(1, MarginLoan::query()->where('margin_account_id', $account->id)->count());
        $this->assertSame('margin_cross', data_get($marginOrder->spotOrder->metadata, 'account_type'));
    }

    public function test_margin_auto_borrow_is_unwound_when_spot_order_submission_fails(): void
    {
        $user = $this->seedUserFunding('200');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '200', 'phase6b-auto-borrow-unwind-collateral');

        try {
            app(MarginOrderService::class)->place($account, [
                'client_order_id' => 'margin-auto-borrow-unwind',
                'pair' => 'BTC/USDT',
                'side' => 'buy',
                'type' => 'limit',
                'amount' => '0.01005',
                'price' => '50000',
                'borrow_mode' => 'AUTO_BORROW',
            ]);
            $this->fail('Expected invalid Spot precision to reject the Margin order.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('lot size', strtolower($exception->getMessage()));
        }

        $this->assertSame(0, MarginLoan::query()->where('margin_account_id', $account->id)->count());
        $this->assertSame('1000.000000000000000000', (string) MarginLendingPool::query()->where('asset', 'USDT')->value('available_liquidity'));
        $this->assertSame(0, LedgerEntry::query()->where('reference', 'margin-borrow:margin-order-auto-borrow:margin-auto-borrow-unwind')->count());
    }

    public function test_margin_auto_borrow_unused_liquidity_is_repaid_after_order_cancel(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);
        app(LedgerService::class)->getOrCreateAccount(null, 'treasury', 'BTC')->update(['balance' => '1000000']);
        app(LedgerService::class)->fiatDeposit($user->id, '0.02', 'BTC', 'phase6b-auto-borrow-cancel-btc-' . $user->id);
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        $this->fundPool('USDT', '1000');
        app(MarginTransferService::class)->transferInto($account, 'funding', 'BTC', '0.02', 'phase6b-auto-borrow-cancel-collateral');

        $marginOrder = app(MarginOrderService::class)->place($account, [
            'client_order_id' => 'margin-auto-borrow-cancel',
            'pair' => 'BTC/USDT',
            'side' => 'buy',
            'type' => 'limit',
            'amount' => '0.01',
            'price' => '50000',
            'borrow_mode' => 'AUTO_BORROW',
        ]);

        $this->assertSame('open', $marginOrder->spotOrder->fresh()->status);
        $this->assertSame('500.000000000000000000', (string) $marginOrder->auto_borrow_amount);
        $this->assertSame('500.000000000000000000', (string) MarginLendingPool::query()->where('asset', 'USDT')->value('available_liquidity'));

        $cancelled = app(MarginOrderService::class)->cancel($user->id, $marginOrder->margin_order_uuid);

        $this->assertSame(MarginOrder::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('cancelled', $cancelled->spotOrder->fresh()->status);
        $this->assertSame(MarginLoan::STATUS_REPAID, MarginLoan::query()->where('margin_account_id', $account->id)->firstOrFail()->status);
        $this->assertSame('1000.000000000000000000', (string) MarginLendingPool::query()->where('asset', 'USDT')->value('available_liquidity'));
        $this->assertSame('USDT', data_get($cancelled->metadata, 'auto_borrow_release.asset'));
        $this->assertSame('500.000000000000000000', data_get($cancelled->metadata, 'auto_borrow_release.amount'));
    }

    public function test_margin_spot_fill_settles_into_margin_account(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        app(\App\Services\TradeService::class)->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.01', '50000');

        $buyer = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($buyer->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '1000', 'phase6b-fill-collateral');

        $marginOrder = app(MarginOrderService::class)->place($account, [
            'client_order_id' => 'margin-fill-one',
            'pair' => 'BTC/USDT',
            'side' => 'buy',
            'type' => 'limit',
            'amount' => '0.01',
            'price' => '50000',
            'borrow_mode' => 'NORMAL',
        ]);

        $this->assertSame('filled', $marginOrder->spotOrder->fresh()->status);
        $this->assertTrue(bccomp(app(LedgerService::class)->getBalance($buyer->id, 'BTC', 'margin_cross'), '0', 18) > 0);
        $this->assertSame(0, bccomp(app(LedgerService::class)->getBalance($buyer->id, 'BTC', 'unified_trading'), '0', 18));
    }

    public function test_margin_auto_repay_uses_received_asset_from_immediate_fill(): void
    {
        $buyer = $this->fundTrading('USDT', '1000');
        app(\App\Services\TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.01', '50000');

        $seller = $this->seedUserFunding('1000');
        app(LedgerService::class)->fiatDeposit($seller->id, '0.01', 'BTC', 'phase6b-btc-collateral-' . $seller->id);
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($seller->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'BTC', '0.01', 'phase6b-auto-repay-btc-in');
        $this->fundPool('USDT', '1000');
        MarginLendingPool::query()->where('asset', 'USDT')->update([
            'available_liquidity' => '900.000000000000000000',
            'borrowed_liquidity' => '100.000000000000000000',
        ]);
        $loan = MarginLoan::query()->create([
            'loan_uuid' => (string) Str::uuid(),
            'margin_account_id' => $account->id,
            'user_id' => $seller->id,
            'asset' => 'USDT',
            'principal' => '100.000000000000000000',
            'accrued_interest' => '0',
            'interest_rate' => '0.10000000',
            'opened_at' => now()->subHour(),
            'last_accrual_at' => now(),
            'status' => MarginLoan::STATUS_ACTIVE,
            'idempotency_key' => 'manual-auto-repay-loan',
            'metadata' => ['test' => true],
        ]);

        $marginOrder = app(MarginOrderService::class)->place($account, [
            'client_order_id' => 'margin-auto-repay-one',
            'pair' => 'BTC/USDT',
            'side' => 'sell',
            'type' => 'limit',
            'amount' => '0.01',
            'price' => '50000',
            'borrow_mode' => 'AUTO_REPAY',
        ])->fresh();

        $this->assertSame('filled', $marginOrder->spotOrder->fresh()->status);
        $this->assertSame('USDT', $marginOrder->auto_repay_asset);
        $this->assertSame('100.000000000000000000', (string) $marginOrder->auto_repay_amount);
        $this->assertSame(MarginLoan::STATUS_REPAID, MarginLoan::query()->where('margin_account_id', $account->id)->firstOrFail()->status);
        $this->assertSame('1000.000000000000000000', (string) MarginLendingPool::query()->where('asset', 'USDT')->value('available_liquidity'));
    }

    public function test_margin_auto_repay_runs_after_later_spot_fill(): void
    {
        $seller = $this->seedUserFunding('1000');
        app(LedgerService::class)->fiatDeposit($seller->id, '0.01', 'BTC', 'phase6b-async-repay-btc-' . $seller->id);
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($seller->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'BTC', '0.01', 'phase6b-async-repay-btc-in');
        $this->fundPool('USDT', '1000');
        MarginLendingPool::query()->where('asset', 'USDT')->update([
            'available_liquidity' => '900.000000000000000000',
            'borrowed_liquidity' => '100.000000000000000000',
        ]);
        $loan = MarginLoan::query()->create([
            'loan_uuid' => (string) Str::uuid(),
            'margin_account_id' => $account->id,
            'user_id' => $seller->id,
            'asset' => 'USDT',
            'principal' => '100.000000000000000000',
            'accrued_interest' => '0',
            'interest_rate' => '0.10000000',
            'opened_at' => now()->subHour(),
            'last_accrual_at' => now(),
            'status' => MarginLoan::STATUS_ACTIVE,
            'idempotency_key' => 'manual-async-auto-repay-loan',
            'metadata' => ['test' => true],
        ]);

        $marginOrder = app(MarginOrderService::class)->place($account, [
            'client_order_id' => 'margin-async-auto-repay-one',
            'pair' => 'BTC/USDT',
            'side' => 'sell',
            'type' => 'limit',
            'amount' => '0.01',
            'price' => '50000',
            'borrow_mode' => 'AUTO_REPAY',
        ]);
        $this->assertSame('open', $marginOrder->spotOrder->fresh()->status);
        $this->assertSame(MarginLoan::STATUS_ACTIVE, $loan->fresh()->status);

        $buyer = $this->fundTrading('USDT', '1000');
        app(\App\Services\TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.01', '50000');

        $this->assertSame(MarginLoan::STATUS_REPAID, $loan->fresh()->status);
        $this->assertSame('100.000000000000000000', (string) $marginOrder->fresh()->auto_repay_amount);
    }

    public function test_margin_realtime_events_are_durable_and_resyncable(): void
    {
        $user = $this->seedUserFunding('1000');
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'USDT', '100', 'phase6b-realtime-transfer');

        $events = MarginRealtimeEvent::query()->where('user_id', $user->id)->orderBy('sequence')->get();
        $this->assertNotEmpty($events);
        $this->assertSame(1, (int) $events->first()->sequence);

        $this->actingAs($user)
            ->getJson('/api/margin/realtime/snapshot?after_sequence=0')
            ->assertOk()
            ->assertJsonPath('data.latest_sequence', (int) $events->last()->sequence)
            ->assertJsonFragment(['event' => 'margin.account.transferred_in']);
    }

    public function test_margin_readiness_reports_funded_liquidity_recovery_and_load_pass(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Readiness Admin',
            'email' => 'margin-readiness@example.test',
            'password' => 'secret',
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $this->fundPool('USDT', '1000');
        $this->fundPool('BTC', '1');
        app(MarginOperationalReadinessService::class)->runLoadProbe(5);

        $this->assertSame('PASS', MarginLoadRun::query()->latest('id')->firstOrFail()->status);

        $this->actingAs($admin)
            ->getJson('/api/admin/margin/readiness')
            ->assertOk()
            ->assertJsonPath('data.margin_backend', 'READY')
            ->assertJsonPath('data.margin_realtime', 'READY')
            ->assertJsonPath('data.auto_repay', 'READY')
            ->assertJsonPath('data.restart_recovery', 'PASS')
            ->assertJsonPath('data.load_stress', 'PASS')
            ->assertJsonPath('data.real_lending_liquidity_funded', 'YES')
            ->assertJsonPath('data.safe_to_begin_phase7', 'YES');
    }

    public function test_margin_liquidation_execution_sells_collateral_through_spot_and_repays_debt(): void
    {
        Config::set('margin.reference_prices.LQBTC', '50000');
        $this->asset('LQBTC', true, true, '0.80000000');
        $this->market('LQBTC/USDT', 'LQBTC');

        $buyer = $this->fundTrading('USDT', '1000');
        app(\App\Services\TradeService::class)->placeOrder($buyer->id, 'LQBTC/USDT', 'buy', 'limit', '0.01', '50000');

        $user = $this->seedUserFunding('1000');
        app(LedgerService::class)->getOrCreateAccount(null, 'treasury', 'LQBTC')->update(['balance' => '1000000']);
        app(LedgerService::class)->fiatDeposit($user->id, '0.01', 'LQBTC', 'phase6b-liquidation-lqbtc-' . $user->id);
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'LQBTC', '0.01', 'phase6b-liquidation-lqbtc-in');
        $this->fundPool('USDT', '1000');
        MarginLendingPool::query()->where('asset', 'USDT')->update([
            'available_liquidity' => '0.000000000000000000',
            'borrowed_liquidity' => '1000.000000000000000000',
        ]);
        $loan = MarginLoan::query()->create([
            'loan_uuid' => (string) Str::uuid(),
            'margin_account_id' => $account->id,
            'user_id' => $user->id,
            'asset' => 'USDT',
            'principal' => '1000.000000000000000000',
            'accrued_interest' => '0',
            'interest_rate' => '0.10000000',
            'opened_at' => now()->subHour(),
            'last_accrual_at' => now(),
            'status' => MarginLoan::STATUS_ACTIVE,
            'idempotency_key' => 'manual-liquidation-loan',
            'metadata' => ['test' => true],
        ]);

        $liquidation = app(MarginLiquidationService::class)->openIfUnsafe($account);
        $executed = app(MarginLiquidationService::class)->execute($liquidation, 'phase6b-liquidation-exec');
        $loan = MarginLoan::query()->where('margin_account_id', $account->id)->firstOrFail();

        $this->assertSame('PARTIALLY_EXECUTED', $executed->status, json_encode($executed->metadata));
        $this->assertSame(MarginLoan::STATUS_PARTIALLY_REPAID, $loan->status);
        $this->assertSame('501.000000000000000000', (string) $loan->principal);
        $this->assertNotEmpty($executed->assets_sold);
        $this->assertNotEmpty($executed->debt_repaid);
        $this->assertSame('499.000000000000000000', (string) MarginLendingPool::query()->where('asset', 'USDT')->value('available_liquidity'));
    }

    public function test_admin_can_execute_open_margin_liquidation_through_api(): void
    {
        $roleId = \DB::table('roles')->insertGetId(['name' => 'super_admin', 'created_at' => now(), 'updated_at' => now()]);
        $admin = Admin::query()->create([
            'name' => 'Margin Admin',
            'email' => 'margin-admin@example.test',
            'password' => 'secret',
            'role_id' => $roleId,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $buyer = $this->fundTrading('USDT', '1000');
        app(\App\Services\TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.01', '50000');

        $user = $this->seedUserFunding('1000');
        app(LedgerService::class)->fiatDeposit($user->id, '0.01', 'BTC', 'phase6b-api-liquidation-btc-' . $user->id);
        $account = app(MarginAccountService::class)->getOrCreateCrossAccount($user->id);
        app(MarginTransferService::class)->transferInto($account, 'funding', 'BTC', '0.01', 'phase6b-api-liquidation-btc-in');
        $this->fundPool('USDT', '1000');
        MarginLendingPool::query()->where('asset', 'USDT')->update([
            'available_liquidity' => '0.000000000000000000',
            'borrowed_liquidity' => '1000.000000000000000000',
        ]);
        $loan = MarginLoan::query()->create([
            'loan_uuid' => (string) Str::uuid(),
            'margin_account_id' => $account->id,
            'user_id' => $user->id,
            'asset' => 'USDT',
            'principal' => '1000.000000000000000000',
            'accrued_interest' => '0',
            'interest_rate' => '0.10000000',
            'opened_at' => now()->subHour(),
            'last_accrual_at' => now(),
            'status' => MarginLoan::STATUS_ACTIVE,
            'idempotency_key' => 'manual-api-liquidation-loan',
            'metadata' => ['test' => true],
        ]);

        $liquidation = app(MarginLiquidationService::class)->openIfUnsafe($account);

        $this->actingAs($admin)
            ->postJson("/api/admin/margin/liquidations/{$liquidation->liquidation_id}/execute", [
                'idempotency_key' => 'phase6b-liquidation-api-exec',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PARTIALLY_EXECUTED')
            ->assertJsonFragment(['liquidation_id' => $liquidation->liquidation_id]);
    }

    private function seedUserFunding(string $amount): User
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '1000000']);
        $ledger->fiatDeposit($user->id, $amount, 'USDT', 'phase6-seed-' . $user->id . '-' . uniqid('', true));

        return $user;
    }

    private function fundTrading(string $asset, string $amount): User
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->update(['balance' => '1000000']);
        $ledger->fiatDeposit($user->id, $amount, $asset, 'phase6b-seed-' . $user->id . '-' . $asset);
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, 'phase6b-trading-' . $user->id . '-' . $asset);

        return $user;
    }

    private function fundPool(string $asset, string $amount): void
    {
        app(LedgerService::class)->getOrCreateAccount(null, 'treasury', $asset)->update(['balance' => '1000000']);
        app(MarginLiquidityService::class)->fundPool($asset, $amount, 'phase6-pool-' . $asset . '-' . uniqid('', true));
    }

    private function asset(string $asset, bool $borrow, bool $collateral, string $collateralFactor): void
    {
        MarginAssetConfig::query()->updateOrCreate(['asset' => $asset], [
            'borrow_enabled' => $borrow,
            'collateral_enabled' => $collateral,
            'collateral_factor' => $collateralFactor,
            'liquidation_factor' => '0.85000000',
            'borrow_limit' => '1000000',
            'minimum_borrow' => '1',
            'maximum_borrow' => '1000000',
            'reserve_factor' => '0.10000000',
            'interest_model' => 'kinked_utilization',
            'base_rate' => '0.02000000',
            'slope_1' => '0.08000000',
            'optimal_utilization' => '0.80000000',
            'slope_2' => '0.50000000',
            'max_rate' => '1.00000000',
            'status' => 'ENABLED',
        ]);
    }

    private function market(string $symbol = 'BTC/USDT', string $base = 'BTC'): void
    {
        Market::query()->updateOrCreate(['symbol' => $symbol], [
            'base_currency' => $base,
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
}
