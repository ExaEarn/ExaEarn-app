<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FiatWithdrawalBeneficiary;
use App\Models\FiatWithdrawalIntent;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FiatWithdrawalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_review_verify_and_submit_fiat_withdrawal_with_ledger_reservation(): void
    {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();
        config()->set('services.fiat_gateway.primary', 'sandbox');
        config()->set('wallet.fiat_withdrawals.minimum', '100');
        config()->set('wallet.fiat_withdrawals.maximum', '100000');
        config()->set('wallet.fiat_withdrawals.daily_limit', '100000');
        config()->set('wallet.fiat_withdrawals.percent_fee', '0.005');
        config()->set('wallet.fiat_withdrawals.flat_fee.NGN', '100');

        $user = User::factory()->create();
        WalletBalance::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'NGN',
            'balance' => '100000.00000000',
        ]);

        $meta = $this->actingAs($user)->getJson('/api/fiat-withdrawals/meta?currency=NGN');
        $meta->assertOk()->assertJsonPath('data.balance.available', '100000.00000000');

        $banks = $this->actingAs($user)->getJson('/api/fiat-withdrawals/banks?country=NG&currency=NGN');
        $banks->assertOk()->assertJsonPath('data.items.1.name', 'GTBank');

        $resolved = $this->actingAs($user)->postJson('/api/fiat-withdrawals/resolve-account', [
            'country' => 'NG',
            'currency' => 'NGN',
            'bank_code' => '058',
            'account_number' => '1234567890',
        ]);
        $resolved->assertOk()->assertJsonPath('data.verified', true);

        $quote = $this->actingAs($user)->postJson('/api/fiat-withdrawals/quote', [
            'source_account' => 'funding',
            'currency' => 'NGN',
            'amount' => '10000',
        ]);
        $quote->assertOk()
            ->assertJsonPath('data.fee', '150.00000000')
            ->assertJsonPath('data.recipient_receives', '9850.00000000');

        $intentResponse = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'fiat-withdraw-test-1')
            ->postJson('/api/fiat-withdrawals/intents', [
                'source_account' => 'funding',
                'country' => 'NG',
                'currency' => 'NGN',
                'amount' => '10000',
                'bank_code' => '058',
                'bank_name' => 'GTBank',
                'account_number' => '1234567890',
                'account_name' => 'Verified ExaEarn User 7890',
                'narration' => 'ExaEarn Withdrawal',
                'save_beneficiary' => true,
                'is_default_beneficiary' => true,
            ]);

        $intentResponse->assertCreated()->assertJsonPath('data.intent.status', 'awaiting_verification');
        $uuid = $intentResponse->json('data.intent.uuid');

        $challenge = $this->actingAs($user)->postJson("/api/fiat-withdrawals/intents/{$uuid}/verification-challenges", [
            'method' => 'email',
        ]);
        $challenge->assertCreated();
        $code = $challenge->json('data.development_code');

        $verified = $this->actingAs($user)->postJson("/api/fiat-withdrawals/intents/{$uuid}/verify", [
            'method' => 'email',
            'code' => $code,
        ]);
        $verified->assertOk()->assertJsonPath('data.intent.status', 'processing');

        $this->assertDatabaseHas('fiat_withdrawal_beneficiaries', [
            'user_id' => $user->id,
            'currency' => 'NGN',
            'bank_name' => 'GTBank',
            'is_default' => true,
        ]);

        $intent = FiatWithdrawalIntent::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertNotNull($intent->reserve_ledger_reference);
        $this->assertDatabaseHas('ledger_entries', [
            'reference' => $intent->reserve_ledger_reference,
            'transaction_type' => 'fiat_withdrawal_reserve',
        ]);
        $this->assertSame(1, FiatWithdrawalBeneficiary::query()->where('user_id', $user->id)->count());
    }
}
