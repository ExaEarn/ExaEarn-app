<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CalculateRewardJob;
use App\Models\LedgerTransaction;
use App\Models\Market;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\SpotCutoverJournal;
use App\Models\SpotEngineSequence;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Spot\SpotCutoverReadinessService;
use App\Services\Spot\SpotCutoverService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase2CControlledCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trading.engine.default_mode', 'legacy');
        Config::set('trading.engine.market_overrides', []);
        Queue::fake([CalculateRewardJob::class]);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $this->market('BTC/USDT', 'BTC', 'USDT', 'new', 'NEW');
        $this->market('ETH/USDT', 'ETH', 'USDT', 'legacy', 'LEGACY');
    }

    public function test_per_market_authority_routes_new_and_legacy_independently(): void
    {
        $btcSeller = $this->fundTrading('BTC', '1');
        $btcBuyer = $this->fundTrading('USDT', '50000');
        $ethSeller = $this->fundTrading('ETH', '5');
        $service = app(TradeService::class);

        $service->placeOrder($btcSeller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $service->placeOrder($btcBuyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');
        $ethOrder = $service->placeOrder($ethSeller->id, 'ETH/USDT', 'sell', 'limit', '1', '3000')['order'];

        $btc = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $eth = Market::query()->where('symbol', 'ETH/USDT')->firstOrFail();

        $this->assertSame(2, (int) SpotEngineSequence::query()->where('market_id', $btc->id)->firstOrFail()->last_sequence);
        $this->assertNull(SpotEngineSequence::query()->where('market_id', $eth->id)->first());
        $this->assertNull($ethOrder->sequence);
    }

    public function test_halted_market_rejects_new_order_entry(): void
    {
        $market = Market::query()->where('symbol', 'ETH/USDT')->firstOrFail();
        $market->forceFill(['engine_mode' => 'halted', 'cutover_state' => 'HALTED_FOR_CUTOVER'])->save();
        $user = $this->fundTrading('USDT', '1000');

        $this->expectException(RuntimeException::class);
        app(TradeService::class)->placeOrder($user->id, 'ETH/USDT', 'buy', 'limit', '1', '100');
    }

    public function test_stale_legacy_matcher_cannot_execute_after_market_becomes_new(): void
    {
        $market = Market::query()->where('symbol', 'ETH/USDT')->firstOrFail();
        $user = $this->fundTrading('ETH', '1');
        $order = app(TradeService::class)->placeOrder($user->id, 'ETH/USDT', 'sell', 'limit', '1', '3000')['order'];

        $market->forceFill(['engine_mode' => 'new', 'cutover_state' => 'NEW'])->save();

        $method = new \ReflectionMethod(TradeService::class, 'matchOrder');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $method->invoke(app(TradeService::class), $order->fresh());
    }

    public function test_cutover_cancel_releases_only_remaining_partial_fill_reservation(): void
    {
        $market = Market::query()->where('symbol', 'ETH/USDT')->firstOrFail();
        $seller = $this->fundTrading('ETH', '2');
        $buyer = $this->fundTrading('USDT', '3000');
        $service = app(TradeService::class);

        $sell = $service->placeOrder($seller->id, 'ETH/USDT', 'sell', 'limit', '2', '1000')['order'];
        $service->placeOrder($buyer->id, 'ETH/USDT', 'buy', 'limit', '1', '1000');
        $remainingSell = $sell->fresh();

        $this->assertSame('1.00000000', (string) $remainingSell->locked_amount);
        app(SpotCutoverService::class)->cancelLegacyOpenOrders($market);

        $reservationId = (string) data_get($remainingSell->metadata, 'reservation_id');
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $this->assertSame(Reservation::STATUS_RELEASED, $reservation->status);
        $this->assertSame('0.000000000000000000', (string) $reservation->remaining_amount);
        $this->assertSame('0.00000000', (string) $remainingSell->fresh()->locked_amount);
    }

    public function test_readiness_precheck_and_cutover_journal_transitions(): void
    {
        $market = Market::query()->where('symbol', 'ETH/USDT')->firstOrFail();
        $readiness = app(SpotCutoverReadinessService::class)->evaluate($market);

        $this->assertTrue($readiness['ready'], implode(', ', $readiness['blockers']));

        $cutover = app(SpotCutoverService::class);
        $cutover->transition($market, 'SHADOW', 'start shadow validation');
        $result = $cutover->prepareCutover($market->fresh(), 'phase2c test cutover');
        $journal = $cutover->promote($market->fresh(), 'phase2c test promote');

        $this->assertSame('new', $market->fresh()->engine_mode);
        $this->assertSame('NEW', $market->fresh()->cutover_state);
        $this->assertSame('NEW', $journal->new_state);
        $this->assertSame(0, $result['cancelled_legacy_orders']);
        $this->assertGreaterThanOrEqual(4, SpotCutoverJournal::query()->where('market_id', $market->id)->count());
    }

    public function test_canary_trade_settles_and_replays_on_new_market(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $result = app(SpotCutoverService::class)->runCanary($market, '0.1', '1000');

        $this->assertSame('settled', $result['settlement_status']);
        $this->assertSame(1, $result['ledger_transactions']);
        $this->assertSame('settled', $result['outbox_status']);
    }

    public function test_controlled_rollback_sets_rollback_only_without_duplicate_settlement(): void
    {
        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        app(SpotCutoverService::class)->runCanary($market, '0.1', '1000');
        $ledgerBefore = LedgerTransaction::query()->count();

        $result = app(SpotCutoverService::class)->rollback($market, 'phase2c rollback test');

        $this->assertSame('rollback_only', $market->fresh()->engine_mode);
        $this->assertSame('ROLLBACK_ONLY', $market->fresh()->cutover_state);
        $this->assertSame($ledgerBefore, LedgerTransaction::query()->count());
        $this->assertArrayHasKey('cancelled_orders', $result);
    }

    public function test_cross_user_cancel_is_rejected_for_new_engine_orders(): void
    {
        $owner = $this->fundTrading('BTC', '1');
        $intruder = User::factory()->create();
        $order = app(TradeService::class)->placeOrder($owner->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000')['order'];

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(TradeService::class)->cancelOrder($intruder->id, $order->order_uuid);
    }

    private function market(string $symbol, string $base, string $quote, string $mode, string $state): void
    {
        Market::query()->create([
            'symbol' => $symbol,
            'base_currency' => $base,
            'quote_currency' => $quote,
            'status' => 'active',
            'trading_status' => 'trading',
            'engine_mode' => $mode,
            'cutover_state' => $state,
            'health_status' => 'HEALTHY',
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
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase2c-seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, "phase2c-trading-{$user->id}-{$asset}");

        return $user;
    }
}
