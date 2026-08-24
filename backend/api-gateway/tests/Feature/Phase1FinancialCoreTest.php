<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\BalanceProjectionService;
use App\Services\LedgerReconciliationService;
use App\Services\LedgerReversalService;
use App\Services\LedgerService;
use App\Services\ReservationService;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase1FinancialCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::shouldReceive('publish')->zeroOrMoreTimes();
    }

    public function test_ledger_balances_multi_asset_transactions_per_asset(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $ledger = app(LedgerService::class);

        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '10000']);
        $ledger->getOrCreateAccount(null, 'treasury', 'BTC')->update(['balance' => '10']);
        $ledger->fiatDeposit($buyer->id, '1000', 'USDT', 'seed-buyer-usdt');
        $ledger->fiatDeposit($seller->id, '1', 'BTC', 'seed-seller-btc');
        $ledger->internalTransfer($buyer->id, 'funding', 'unified_trading', '1000', 'USDT', 'buyer-to-trading');
        $ledger->internalTransfer($seller->id, 'funding', 'unified_trading', '1', 'BTC', 'seller-to-trading');

        app(SettlementService::class)->spotTrade([
            'buyer_user_id' => $buyer->id,
            'seller_user_id' => $seller->id,
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'base_amount' => '0.100000000000000000',
            'quote_amount' => '500.000000000000000000',
            'buyer_fee' => '0.001000000000000000',
            'seller_fee' => '1.000000000000000000',
        ], 'spot-settle-1');

        $this->assertSame('500.000000000000000000', $ledger->getBalance($buyer->id, 'USDT', 'unified_trading'));
        $this->assertSame('0.099000000000000000', $ledger->getBalance($buyer->id, 'BTC', 'unified_trading'));
        $this->assertSame('499.000000000000000000', $ledger->getBalance($seller->id, 'USDT', 'unified_trading'));
        $this->assertSame('0.900000000000000000', $ledger->getBalance($seller->id, 'BTC', 'unified_trading'));
    }

    public function test_reservation_reduces_available_balance_and_can_be_consumed(): void
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '1000']);
        $ledger->fiatDeposit($user->id, '1000', 'USDT', 'seed-reserve');

        $account = $ledger->getOrCreateAccount($user->id, 'funding', 'USDT');
        $reservation = app(ReservationService::class)->reserve($account->id, 'USDT', '800', 'test_hold', 'test', 'A', 'reserve-A');
        $projection = app(BalanceProjectionService::class)->accountProjection($account->fresh());

        $this->assertSame('1000.000000000000000000', $projection['total']);
        $this->assertSame('800.000000000000000000', $projection['reserved']);
        $this->assertSame('200.000000000000000000', $projection['available']);

        app(ReservationService::class)->consume($reservation->reservation_id, '300');

        $this->assertDatabaseHas('reservations', [
            'reservation_id' => $reservation->reservation_id,
            'status' => Reservation::STATUS_PARTIALLY_CONSUMED,
            'remaining_amount' => '500.000000000000000000',
        ]);
    }

    public function test_duplicate_reservation_idempotency_returns_original_reservation(): void
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '1000']);
        $ledger->fiatDeposit($user->id, '1000', 'USDT', 'seed-idem');
        $account = $ledger->getOrCreateAccount($user->id, 'funding', 'USDT');

        $first = app(ReservationService::class)->reserve($account->id, 'USDT', '300', 'order', 'order', '1', 'same-key');
        $second = app(ReservationService::class)->reserve($account->id, 'USDT', '300', 'order', 'order', '1', 'same-key');

        $this->assertSame($first->reservation_id, $second->reservation_id);
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_reservation_rejects_insufficient_available_balance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient available balance for reservation.');

        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '1000']);
        $ledger->fiatDeposit($user->id, '1000', 'USDT', 'seed-insufficient');
        $account = $ledger->getOrCreateAccount($user->id, 'funding', 'USDT');

        app(ReservationService::class)->reserve($account->id, 'USDT', '800', 'order', 'order', 'A', 'reserve-a');
        app(ReservationService::class)->reserve($account->id, 'USDT', '800', 'order', 'order', 'B', 'reserve-b');
    }

    public function test_reversal_posts_opposite_entries_without_deleting_original(): void
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '1000']);
        $ledger->fiatDeposit($user->id, '100', 'USDT', 'seed-reversal');

        app(LedgerReversalService::class)->reverse('seed-reversal', 'reverse-seed-reversal', 'test reversal', 'system');

        $this->assertSame(2, LedgerTransaction::query()->count());
        $this->assertSame(4, LedgerEntry::query()->count());
        $this->assertSame('0.000000000000000000', $ledger->getBalance($user->id, 'USDT'));
        $this->assertDatabaseHas('ledger_transactions', [
            'reference' => 'seed-reversal',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'reference' => 'reverse-seed-reversal',
            'status' => 'completed',
        ]);
    }

    public function test_reconciliation_detects_legacy_wallet_balance_mismatch(): void
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '1000']);
        $ledger->fiatDeposit($user->id, '100', 'USDT', 'seed-reconcile');

        WalletBalance::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '75.00000000',
        ]);

        $report = app(LedgerReconciliationService::class)->run($user->id);

        $this->assertNotEmpty($report['legacy_projection_mismatches']);
        $this->assertSame([], $report['balanced_transaction_failures']);
    }
}
