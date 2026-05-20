<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\TreasuryBalance;
use App\Models\User;
use App\Services\FeeCalculator;
use App\Services\FeeTreasuryService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FeeTreasurySystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_fee_debits_user_sends_net_and_credits_system_treasury(): void
    {
        Redis::shouldReceive('publish')->times(3);
        config()->set('fees.withdrawal.bps.USDT', 10);
        config()->set('fees.withdrawal.fixed.USDT', '1');

        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount($user->id, 'funding', 'USDT')->update(['balance' => '100']);

        $result = app(FeeTreasuryService::class)->collectWithdrawal($user->id, '50', 'USDT', 'wd_fee_1');

        $this->assertSame('completed', $result['ledger_transaction']->status);
        $this->assertSame('1.050000000000000000', $result['fee']['fee_amount']);
        $this->assertSame('48.950000000000000000', $result['net_payout']);

        $this->assertSame('50.000000000000000000', $ledger->getBalance($user->id, 'USDT'));
        $this->assertDatabaseHas('accounts', ['account_type' => 'system_treasury', 'asset' => 'USDT']);
        $this->assertDatabaseHas('ledger_entries', [
            'reference' => 'wd_fee_1',
            'transaction_type' => 'withdrawal_fee',
            'amount' => '1.050000000000000000',
        ]);

        $treasury = TreasuryBalance::query()->where('asset', 'USDT')->firstOrFail();
        $this->assertSame('1.050000000000000000', (string) $treasury->hot_wallet_balance);
    }

    public function test_spot_fee_is_atomic_and_posts_user_debit_to_treasury_credit(): void
    {
        Redis::shouldReceive('publish')->times(2);
        config()->set('fees.spot.taker_bps', 20);

        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount($user->id, 'spot', 'BTC')->update(['balance' => '1']);

        app(FeeTreasuryService::class)->collectSpot($user->id, '0.5', 'BTC', 'spot_fee_1', 'taker');

        $entries = LedgerEntry::query()->where('reference', 'spot_fee_1')->orderBy('amount')->get();
        $this->assertCount(2, $entries);
        $this->assertSame('-0.001000000000000000', (string) $entries[0]->amount);
        $this->assertSame('0.001000000000000000', (string) $entries[1]->amount);
        $this->assertSame('0.999000000000000000', $ledger->getBalance($user->id, 'BTC', 'spot'));
    }

    public function test_fiat_deposit_service_charge_credits_net_to_user_and_fee_to_treasury(): void
    {
        Redis::shouldReceive('publish')->times(3);
        config()->set('fees.fiat_deposit.bps.NGN', 150);
        config()->set('fees.fiat_deposit.fixed.NGN', '25');

        $user = User::factory()->create();

        $result = app(FeeTreasuryService::class)->collectFiatDeposit($user->id, '10000', 'NGN', 'fiat_fee_1');

        $this->assertSame('175.000000000000000000', $result['fee']['fee_amount']);
        $this->assertSame('9825.000000000000000000', $result['net_deposit']);
        $this->assertDatabaseHas('accounts', ['account_type' => 'system_treasury', 'asset' => 'NGN']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'fee.fiat_deposit_collected']);
    }

    public function test_futures_fee_is_deducted_from_margin_account(): void
    {
        Redis::shouldReceive('publish')->times(2);
        config()->set('fees.futures.taker_bps', 5);

        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount($user->id, 'futures', 'USDT')->update(['balance' => '250']);

        app(FeeTreasuryService::class)->collectFutures($user->id, '1000', 'futures_fee_1', 'taker');

        $this->assertSame('249.500000000000000000', $ledger->getBalance($user->id, 'USDT', 'futures'));
        $this->assertDatabaseHas('ledger_entries', [
            'reference' => 'futures_fee_1',
            'transaction_type' => 'futures_fee',
            'amount' => '0.500000000000000000',
        ]);
    }

    public function test_fee_calculator_rejects_fee_that_consumes_entire_withdrawal(): void
    {
        config()->set('fees.withdrawal.bps.BTC', 0);
        config()->set('fees.withdrawal.fixed.BTC', '1');

        $this->expectException(\InvalidArgumentException::class);
        app(FeeCalculator::class)->withdrawal('0.5', 'BTC');
    }
}
