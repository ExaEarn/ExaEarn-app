<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CalculateRewardJob;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\Market;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\SpotExecutionEvent;
use App\Models\SpotOrderBookSnapshot;
use App\Models\Trade;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Spot\OrderBookSnapshotService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase2SpotEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trading.engine.mode', 'new');
        Queue::fake([CalculateRewardJob::class]);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

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

    public function test_price_time_priority_fifo_same_price_and_settlement(): void
    {
        $sellerA = $this->fundTrading('BTC', '1');
        $sellerB = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '30000');
        $service = app(TradeService::class);

        $sellA = $service->placeOrder($sellerA->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000')['order'];
        $sellB = $service->placeOrder($sellerB->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000')['order'];
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.15', '100000');

        $this->assertCount(2, $result['trades']);
        $this->assertSame($sellA->order_uuid, data_get($result['trades'][0]->metadata, 'maker_order_uuid'));
        $this->assertSame($sellB->order_uuid, data_get($result['trades'][1]->metadata, 'maker_order_uuid'));
        $this->assertSame('filled', $sellA->fresh()->status);
        $this->assertSame('partially_filled', $sellB->fresh()->status);
        $this->assertSame('filled', $result['order']->fresh()->status);
        $this->assertSame(2, LedgerTransaction::query()->where('transaction_type', 'spot_trade')->count());
        $this->assertSame(2, Trade::query()->where('settlement_status', 'settled')->count());
        $this->assertGreaterThanOrEqual(1, SpotExecutionEvent::query()->where('event_type', 'TRADE_EXECUTED')->count());
    }

    public function test_ioc_cancels_unfilled_remainder(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.2', '100000', ['time_in_force' => 'IOC']);

        $order = $result['order']->fresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('0.10000000', (string) $order->remaining_amount);
        $this->assertSame(Reservation::STATUS_RELEASED, Reservation::query()->where('reservation_id', $order->reservation_id)->firstOrFail()->status);
    }

    public function test_fok_failure_executes_nothing(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.2', '100000', ['time_in_force' => 'FOK']);

        $this->assertSame([], $result['trades']);
        $this->assertSame('cancelled', $result['order']->fresh()->status);
        $this->assertSame(0, Trade::query()->count());
    }

    public function test_market_buy_consumes_liquidity_and_releases_buffer(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'market', '0.1');
        $order = $result['order']->fresh();

        $this->assertCount(1, $result['trades']);
        $this->assertSame('filled', $order->status);
        $this->assertSame('0.00000000', (string) $order->locked_amount);
        $this->assertSame(Reservation::STATUS_RELEASED, Reservation::query()->where('reservation_id', $order->reservation_id)->firstOrFail()->status);
    }

    public function test_market_order_without_liquidity_is_rejected_before_reservation(): void
    {
        $buyer = $this->fundTrading('USDT', '50000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No liquidity available for market order.');
        app(TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'market', '0.1');
    }

    public function test_post_only_rejects_crossing_order_and_accepts_resting_order(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $rejected = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000', ['post_only' => true])['order'];
        $resting = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '99000', ['post_only' => true])['order'];

        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('open', $resting->fresh()->status);
    }

    public function test_duplicate_client_order_id_is_idempotent(): void
    {
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $first = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '90000', ['client_order_id' => 'client-1']);
        $second = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '90000', ['client_order_id' => 'client-1']);

        $this->assertSame($first['order']->order_uuid, $second['order']->order_uuid);
        $this->assertTrue($second['idempotent']);
        $this->assertSame(1, Order::query()->where('client_order_id', 'client-1')->count());
    }

    public function test_cancel_before_opposing_order_prevents_fill(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $sell = $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000')['order'];
        $service->cancelOrder($seller->id, $sell->order_uuid);
        $buy = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000')['order'];

        $this->assertSame('cancelled', $sell->fresh()->status);
        $this->assertSame('open', $buy->fresh()->status);
        $this->assertSame(0, Trade::query()->count());
    }

    public function test_self_trade_prevention_cancels_newest(): void
    {
        $user = $this->fundTrading('BTC', '1');
        $this->fundExistingUserTrading($user, 'USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($user->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $buy = $service->placeOrder($user->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000')['order'];

        $this->assertSame('cancelled', $buy->fresh()->status);
        $this->assertSame(0, Trade::query()->count());
    }

    public function test_snapshot_checksum_replay_is_deterministic(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '50000');
        $service = app(TradeService::class);

        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.2', '100100');
        $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '99000');

        $market = Market::query()->where('symbol', 'BTC/USDT')->firstOrFail();
        $snapshot = SpotOrderBookSnapshot::query()->where('market_id', $market->id)->latest('last_sequence')->firstOrFail();
        $checksum = app(OrderBookSnapshotService::class)->currentChecksum($market, (int) $snapshot->last_sequence);

        $this->assertSame($snapshot->checksum, $checksum);
        $this->assertNotEmpty($snapshot->bids);
        $this->assertNotEmpty($snapshot->asks);
    }

    public function test_precision_rejection(): void
    {
        $buyer = $this->fundTrading('USDT', '50000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order price does not match market tick size.');
        app(TradeService::class)->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000.001');
    }

    private function fundTrading(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $this->fundExistingUserTrading($user, $asset, $amount);
        return $user;
    }

    private function fundExistingUserTrading(User $user, string $asset, string $amount): void
    {
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase2-seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, "phase2-trading-{$user->id}-{$asset}");
    }

    private function systemBalance(string $type, string $asset): string
    {
        return (string) Account::query()->whereNull('user_id')->where('account_type', $type)->where('asset', $asset)->firstOrFail()->balance;
    }
}
