<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FuturesAdlEvent;
use App\Models\FuturesLiquidationEvent;
use App\Models\FuturesMarket;
use App\Models\FuturesPosition;
use App\Models\User;
use App\Services\FuturesAdlService;
use App\Services\FuturesFundingService;
use App\Services\FuturesIndexPriceService;
use App\Services\FuturesMarginService;
use App\Services\FuturesMarkPriceService;
use App\Services\FuturesOrderService;
use App\Services\FuturesPositionService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase5FuturesHardeningTest extends TestCase
{
    use RefreshDatabase;

    private FuturesMarket $market;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('futures.allow_external_execution', false);
        Config::set('futures.engine_mode', 'new');
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
            'last_price' => '100000',
            'index_price' => '100000',
            'mark_price' => '100000',
            'funding_rate' => '0',
            'tick_size' => '0.01',
            'quantity_step' => '0.0001',
            'min_quantity' => '0.0001',
            'max_quantity' => '100',
            'min_notional' => '5',
            'max_notional' => '1000000',
            'risk_tiers' => [
                ['notional_floor' => '0', 'notional_cap' => '50000', 'maintenance_margin_rate' => '0.005', 'maintenance_amount' => '0', 'max_leverage' => 100],
                ['notional_floor' => '50000', 'notional_cap' => '250000', 'maintenance_margin_rate' => '0.01', 'maintenance_amount' => '250', 'max_leverage' => 50],
            ],
            'price_band_bps' => 500,
        ]);
    }

    public function test_tiered_initial_and_maintenance_margin_are_deterministic(): void
    {
        $margin = app(FuturesMarginService::class);
        $notional = $margin->notional('100000', '1');

        $this->assertSame('100000.000000000000000000', $notional);
        $this->assertSame('2000.000000000000000000', $margin->initialMargin($this->market, $notional, 50));
        $this->assertSame('1250.000000000000000000', $margin->maintenanceMargin($this->market, $notional));
    }

    public function test_index_filters_stale_and_outlier_sources_and_mark_is_separate(): void
    {
        $now = Carbon::parse('2026-08-18 12:00:00');
        $index = app(FuturesIndexPriceService::class)->calculate($this->market, [
            ['venue' => 'A', 'price' => '100000', 'timestamp' => $now->copy()->subSeconds(1)],
            ['venue' => 'B', 'price' => '100100', 'timestamp' => $now->copy()->subSeconds(1)],
            ['venue' => 'STALE', 'price' => '100050', 'timestamp' => $now->copy()->subMinute()],
            ['venue' => 'BAD', 'price' => '120000', 'timestamp' => $now],
        ], $now);

        $this->assertSame(2, $index['healthy_count']);
        $this->assertSame('100050.000000000000000000', $index['index_price']);

        $mark = app(FuturesMarkPriceService::class)->calculate($this->market->fresh(), $index['index_price'], '100550');
        $this->assertArrayHasKey('mark_price', $mark);
        $this->assertNotSame($index['index_price'], $mark['mark_price']);
    }

    public function test_funding_payment_is_idempotent_and_ledger_backed(): void
    {
        $user = $this->fundFutures('USDT', '10000');
        $position = $this->position($user, 'long', '1', '100000', '5000');
        app(FuturesPositionService::class)->refreshUnrealizedPnl($position, '100000');
        $time = Carbon::parse('2026-08-18 16:00:00');

        $payment = app(FuturesFundingService::class)->settlePosition($position->fresh(), '0.0001', $time);
        $duplicate = app(FuturesFundingService::class)->settlePosition($position->fresh(), '0.0001', $time);

        $this->assertSame($payment->id, $duplicate->id);
        $this->assertSame('pay', $payment->direction);
        $this->assertDatabaseHas('ledger_transactions', ['reference' => "futures-funding:{$position->id}:{$time->timestamp}", 'status' => 'completed']);
    }

    public function test_reduce_only_post_only_and_external_execution_fail_closed(): void
    {
        $user = $this->fundFutures('USDT', '10000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Post-only futures order would take liquidity.');
        app(FuturesOrderService::class)->placeOrder($user->id, [
            'symbol' => 'BTCUSDT',
            'type' => 'limit',
            'side' => 'long',
            'price' => '100000',
            'quantity' => '0.01',
            'leverage' => 10,
            'post_only' => true,
        ]);
    }

    public function test_reduce_only_requires_opposite_open_position(): void
    {
        $user = $this->fundFutures('USDT', '10000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reduce-only order requires an opposite open position.');
        app(FuturesOrderService::class)->placeOrder($user->id, [
            'symbol' => 'BTCUSDT',
            'type' => 'limit',
            'side' => 'short',
            'price' => '101000',
            'quantity' => '0.01',
            'leverage' => 10,
            'reduce_only' => true,
        ]);
    }

    public function test_order_is_held_in_exaearn_when_external_futures_execution_disabled(): void
    {
        $user = $this->fundFutures('USDT', '10000');
        $order = app(FuturesOrderService::class)->placeOrder($user->id, [
            'symbol' => 'BTCUSDT',
            'type' => 'limit',
            'side' => 'long',
            'price' => '95000',
            'quantity' => '0.01',
            'leverage' => 10,
        ]);

        $this->assertSame('open', $order->fresh()->status);
        $this->assertTrue((bool) data_get($order->fresh()->metadata, 'external_execution_disabled'));
    }

    public function test_liquidation_creates_auditable_event_and_insurance_credit(): void
    {
        $user = $this->fundFutures('USDT', '10000');
        $position = $this->position($user, 'long', '1', '100000', '1000');
        app(FuturesPositionService::class)->refreshUnrealizedPnl($position, '99000');

        $liquidated = app(\App\Services\FuturesLiquidationService::class)->liquidate($position->fresh());

        $this->assertSame('liquidated', $liquidated->status);
        $this->assertGreaterThanOrEqual(1, FuturesLiquidationEvent::query()->where('symbol', 'BTCUSDT')->count());
        $this->assertDatabaseHas('ledger_transactions', ['transaction_type' => 'futures_insurance_credit', 'status' => 'completed']);
    }

    public function test_adl_queue_ranking_is_deterministic(): void
    {
        $userA = $this->fundFutures('USDT', '10000');
        $userB = $this->fundFutures('USDT', '10000');
        $positionA = $this->position($userA, 'short', '1', '100000', '1000');
        $positionB = $this->position($userB, 'short', '1', '100000', '5000');
        $positionA->forceFill(['mark_price' => '90000', 'unrealized_pnl' => '10000'])->save();
        $positionB->forceFill(['mark_price' => '90000', 'unrealized_pnl' => '10000'])->save();

        $queue = app(FuturesAdlService::class)->rankQueue('BTCUSDT', 'short');
        app(FuturesAdlService::class)->queueEvent($queue[0]['position'], '0.5', $queue[0]['rank_score']);

        $this->assertSame($positionA->id, $queue[0]['position']->id);
        $this->assertSame(1, FuturesAdlEvent::query()->where('status', 'queued')->count());
    }

    private function fundFutures(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase5-seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'futures', $amount, $asset, "phase5-futures-{$user->id}-{$asset}");

        return $user;
    }

    private function position(User $user, string $side, string $quantity, string $entry, string $margin): FuturesPosition
    {
        return FuturesPosition::query()->create([
            'user_id' => $user->id,
            'futures_market_id' => $this->market->id,
            'symbol' => $this->market->symbol,
            'side' => $side,
            'entry_price' => $entry,
            'mark_price' => $entry,
            'quantity' => $quantity,
            'leverage' => 20,
            'margin_type' => 'isolated',
            'margin' => $margin,
            'isolated_margin' => $margin,
            'maintenance_margin' => '500',
            'unrealized_pnl' => '0',
            'realized_pnl' => '0',
            'liquidation_price' => '99500',
            'bankruptcy_price' => '99000',
            'status' => 'open',
        ]);
    }
}
