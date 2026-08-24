<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CalculateRewardJob;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\Market;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BalanceProjectionService;
use App\Services\LedgerService;
use App\Services\ReservationService;
use App\Services\SettlementService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class SpotFinancialMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([CalculateRewardJob::class]);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        Market::query()->create([
            'symbol' => 'BTC/USDT',
            'base_currency' => 'BTC',
            'quote_currency' => 'USDT',
            'status' => 'active',
            'last_price' => '100000',
            'price_precision' => '0.01',
            'min_order_size' => '0.0001',
            'max_order_size' => '100',
            'maker_fee' => '0.001',
            'taker_fee' => '0.002',
        ]);
    }

    public function test_limit_buy_and_sell_use_canonical_reservations(): void
    {
        $buyer = $this->fundTrading('USDT', '10000');
        $seller = $this->fundTrading('BTC', '1');
        $service = app(TradeService::class);

        $buy = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.08', '100000')['order'];
        $sell = $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.01', '110000')['order'];

        $this->assertReservation($buy, 'USDT', '8000.000000000000000000');
        $this->assertReservation($sell, 'BTC', '0.010000000000000000');
    }

    public function test_insufficient_and_competing_reservations_cannot_oversubscribe(): void
    {
        $buyer = $this->fundTrading('USDT', '10000');
        $service = app(TradeService::class);
        $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.08', '100000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient available balance for reservation.');
        $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.08', '100000');
    }

    public function test_full_fill_settles_ledger_fees_and_consumes_both_reservations(): void
    {
        $seller = $this->fundTrading('BTC', '1');
        $buyer = $this->fundTrading('USDT', '10000');
        $service = app(TradeService::class);
        $sell = $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000')['order'];
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');
        $buy = $result['order'];

        $this->assertCount(1, $result['trades']);
        $this->assertSame('filled', $buy->status);
        $this->assertSame('filled', $sell->fresh()->status);
        $this->assertSame(Reservation::STATUS_CONSUMED, $this->reservation($buy)->status);
        $this->assertSame(Reservation::STATUS_CONSUMED, $this->reservation($sell)->status);
        $this->assertSame('0.099800000000000000', app(LedgerService::class)->getBalance($buyer->id, 'BTC', 'unified_trading'));
        $this->assertSame('9990.000000000000000000', app(LedgerService::class)->getBalance($seller->id, 'USDT', 'unified_trading'));
        $this->assertSame('0.000200000000000000', $this->systemBalance('fee_revenue', 'BTC'));
        $this->assertSame('10.000000000000000000', $this->systemBalance('fee_revenue', 'USDT'));
    }

    public function test_partial_fill_and_price_improvement_preserve_only_required_remaining_hold(): void
    {
        $seller = $this->fundTrading('BTC', '0.25');
        $buyer = $this->fundTrading('USDT', '100000');
        $service = app(TradeService::class);
        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.25', '99500');
        $result = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '1', '100000');
        $order = $result['order'];
        $reservation = $this->reservation($order);

        $this->assertSame('partially_filled', $order->status);
        $this->assertSame('0.75000000', (string) $order->remaining_amount);
        $this->assertSame('75000.000000000000000000', (string) $reservation->remaining_amount);
        $this->assertSame(Reservation::STATUS_PARTIALLY_CONSUMED, $reservation->status);
        $projection = app(BalanceProjectionService::class)->accountProjection($reservation->account);
        $this->assertSame('125.000000000000000000', $projection['available']);
    }

    public function test_cancel_after_partial_fill_releases_only_remaining_reservation_and_is_idempotent(): void
    {
        $seller = $this->fundTrading('BTC', '0.25');
        $buyer = $this->fundTrading('USDT', '100000');
        $service = app(TradeService::class);
        $service->placeOrder($seller->id, 'BTC/USDT', 'sell', 'limit', '0.25', '100000');
        $order = $service->placeOrder($buyer->id, 'BTC/USDT', 'buy', 'limit', '1', '100000')['order'];

        $first = $service->cancelOrder($buyer->id, $order->order_uuid);
        $second = $service->cancelOrder($buyer->id, $order->order_uuid);

        $this->assertSame('cancelled', $first->status);
        $this->assertSame('cancelled', $second->status);
        $this->assertSame(Reservation::STATUS_RELEASED, $this->reservation($order)->status);
    }

    public function test_duplicate_fill_reference_does_not_duplicate_ledger_or_consumption(): void
    {
        $buyer = $this->fundTrading('USDT', '1000');
        $seller = $this->fundTrading('BTC', '1');
        $reservations = app(ReservationService::class);
        $buyAccount = app(LedgerService::class)->getOrCreateAccount($buyer->id, 'unified_trading', 'USDT');
        $sellAccount = app(LedgerService::class)->getOrCreateAccount($seller->id, 'unified_trading', 'BTC');
        $buy = $reservations->reserve($buyAccount->id, 'USDT', '500', 'spot_order', 'order', 'B', 'B');
        $sell = $reservations->reserve($sellAccount->id, 'BTC', '0.1', 'spot_order', 'order', 'S', 'S');
        $payload = [
            'buyer_user_id' => $buyer->id, 'seller_user_id' => $seller->id,
            'base_asset' => 'BTC', 'quote_asset' => 'USDT',
            'base_amount' => '0.1', 'quote_amount' => '500',
            'buyer_fee' => '0.0002', 'seller_fee' => '1',
            'consume_reservations' => [$buy->reservation_id => '500', $sell->reservation_id => '0.1'],
        ];

        app(SettlementService::class)->spotTrade($payload, 'duplicate-fill');
        app(SettlementService::class)->spotTrade($payload, 'duplicate-fill');

        $this->assertSame(1, LedgerTransaction::query()->where('reference', 'duplicate-fill')->count());
        $this->assertSame(Reservation::STATUS_CONSUMED, $buy->fresh()->status);
        $this->assertSame(Reservation::STATUS_CONSUMED, $sell->fresh()->status);
    }

    public function test_self_trade_is_not_matched(): void
    {
        $user = $this->fundTrading('BTC', '1');
        $this->fundExistingUserTrading($user, 'USDT', '10000');
        $service = app(TradeService::class);
        $service->placeOrder($user->id, 'BTC/USDT', 'sell', 'limit', '0.1', '100000');
        $result = $service->placeOrder($user->id, 'BTC/USDT', 'buy', 'limit', '0.1', '100000');

        $this->assertSame([], $result['trades']);
        $this->assertSame(0, LedgerTransaction::query()->where('transaction_type', 'spot_trade')->count());
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
        $ledger->fiatDeposit($user->id, $amount, $asset, "seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, "trading-{$user->id}-{$asset}");
    }

    private function reservation(Order $order): Reservation
    {
        return Reservation::query()->where('reservation_id', data_get($order->metadata, 'reservation_id'))->firstOrFail();
    }

    private function assertReservation(Order $order, string $asset, string $amount): void
    {
        $reservation = $this->reservation($order);
        $this->assertSame($asset, $reservation->asset);
        $this->assertSame($amount, (string) $reservation->remaining_amount);
        $this->assertSame('spot_order', $reservation->purpose);
    }

    private function systemBalance(string $type, string $asset): string
    {
        return (string) Account::query()->whereNull('user_id')->where('account_type', $type)->where('asset', $asset)->firstOrFail()->balance;
    }
}
