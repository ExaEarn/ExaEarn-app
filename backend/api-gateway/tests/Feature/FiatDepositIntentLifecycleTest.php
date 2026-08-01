<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FiatDepositIntent;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FiatDepositIntentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_mark_paid_and_admin_can_settle_a_fiat_deposit_intent(): void
    {
        Redis::shouldReceive('publish')->times(3);

        config()->set('swap.supported_fiat', ['NGN']);
        config()->set('fees.fiat_deposit.bps.NGN', 150);
        config()->set('fees.fiat_deposit.fixed.NGN', '25');

        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $createResponse = $this->actingAs($user)->postJson('/api/wallet/deposit/fiat-instructions', [
            'method_id' => 'bank_transfer',
            'currency' => 'NGN',
            'amount' => '10000',
        ]);

        $createResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.amount', '10000.000000000000000000')
            ->assertJsonPath('data.fee_amount', '175.000000000000000000')
            ->assertJsonPath('data.net_amount', '9825.000000000000000000');

        $reference = (string) $createResponse->json('data.reference');

        $this->assertDatabaseHas('fiat_deposit_intents', [
            'reference' => $reference,
            'user_id' => $user->id,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson("/api/wallet/deposit/fiat-intents/{$reference}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.intent_status', 'paid');

        $this->assertDatabaseHas('fiat_deposit_intents', [
            'reference' => $reference,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/wallet/deposit/fiat-intents/{$reference}/settle")
            ->assertOk()
            ->assertJsonPath('data.intent_status', 'credited')
            ->assertJsonPath('data.net_amount', '9825.000000000000000000');

        $this->assertDatabaseHas('fiat_deposit_intents', [
            'reference' => $reference,
            'status' => 'credited',
        ]);

        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'transaction_id' => $reference,
            'user_id' => $user->id,
            'currency' => 'NGN',
        ]);

        $historyResponse = $this->actingAs($user)->getJson('/api/wallet/deposit/history?status=completed');
        $historyResponse->assertOk();

        $items = collect($historyResponse->json('data.items', []));
        $matchingItems = $items->where('reference', $reference)->values();

        $this->assertCount(1, $matchingItems);
        $this->assertSame('fiat_intent', $matchingItems[0]['source']);
        $this->assertSame('credited', $matchingItems[0]['status_key']);
    }


    public function test_user_can_create_a_card_payment_fiat_deposit_intent_with_checkout_url(): void
    {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        config()->set('swap.supported_fiat', ['NGN']);
        config()->set('fees.fiat_deposit.bps.NGN', 150);
        config()->set('fees.fiat_deposit.fixed.NGN', '25');

        $user = User::factory()->create([
            'email' => 'card-user@example.com',
            'name' => 'Card User',
        ]);

        $response = $this->actingAs($user)->postJson('/api/wallet/deposit/fiat-instructions', [
            'method_id' => 'card_payment',
            'currency' => 'NGN',
            'amount' => '10000',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.method.id', 'card_payment')
            ->assertJsonPath('data.instructions.type', 'card_payment')
            ->assertJsonPath('data.instructions.provider', 'flutterwave')
            ->assertJsonPath('data.instructions.action_label', 'Continue to secure card checkout');

        $checkoutUrl = (string) $response->json('data.instructions.checkout_url');
        $reference = (string) $response->json('data.reference');

        $this->assertNotSame('', $checkoutUrl);

        $this->assertDatabaseHas('fiat_deposit_intents', [
            'reference' => $reference,
            'user_id' => $user->id,
            'method_id' => 'card_payment',
            'status' => 'pending',
        ]);

        $paymentIntent = PaymentIntent::query()
            ->where('user_id', $user->id)
            ->where('provider', 'flutterwave')
            ->where('currency', 'NGN')
            ->latest('id')
            ->first();

        $this->assertNotNull($paymentIntent);
        $this->assertTrue((bool) preg_match('/^[0-9a-fA-F-]{36}$/', (string) $paymentIntent->intent_id));
        $this->assertSame($reference, data_get($paymentIntent->metadata, 'reference'));
        $this->assertSame($reference, data_get($paymentIntent->metadata, 'fiat_intent_reference'));
        $this->assertSame('pending', $paymentIntent->status);
    }

    public function test_user_can_create_a_payment_gateway_fiat_deposit_intent_with_virtual_account_details(): void
    {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        config()->set('swap.supported_fiat', ['NGN']);
        config()->set('fees.fiat_deposit.bps.NGN', 150);
        config()->set('fees.fiat_deposit.fixed.NGN', '25');

        $user = User::factory()->create([
            'name' => 'Gateway User',
        ]);

        $response = $this->actingAs($user)->postJson('/api/wallet/deposit/fiat-instructions', [
            'method_id' => 'payment_gateway',
            'currency' => 'NGN',
            'amount' => '5000',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.method.id', 'payment_gateway')
            ->assertJsonPath('data.instructions.type', 'payment_gateway')
            ->assertJsonPath('data.instructions.provider', 'nomba')
            ->assertJsonPath('data.instructions.account_number', '1234567890');

        $reference = (string) $response->json('data.reference');

        $this->assertDatabaseHas('fiat_deposit_intents', [
            'reference' => $reference,
            'user_id' => $user->id,
            'method_id' => 'payment_gateway',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('virtual_accounts', [
            'user_id' => $user->id,
            'provider' => 'nomba',
            'account_number' => '1234567890',
            'status' => 'active',
        ]);
    }

    public function test_card_payment_webhook_can_credit_the_related_fiat_deposit_intent(): void
    {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        config()->set('swap.supported_fiat', ['NGN']);
        config()->set('fees.fiat_deposit.bps.NGN', 150);
        config()->set('fees.fiat_deposit.fixed.NGN', '25');
        config()->set('services.flutterwave.webhook_secret', 'flw_secret');

        $user = User::factory()->create([
            'email' => 'credit-user@example.com',
            'name' => 'Credit User',
        ]);

        $createResponse = $this->actingAs($user)->postJson('/api/wallet/deposit/fiat-instructions', [
            'method_id' => 'card_payment',
            'currency' => 'NGN',
            'amount' => '10000',
        ]);

        $reference = (string) $createResponse->json('data.reference');

        $paymentIntent = PaymentIntent::query()
            ->where('user_id', $user->id)
            ->where('provider', 'flutterwave')
            ->where('currency', 'NGN')
            ->latest('id')
            ->firstOrFail();

        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'flw_fiat_card_tx_1',
                'tx_ref' => $paymentIntent->intent_id,
                'amount' => 10000,
                'status' => 'successful',
                'payment_type' => 'card',
            ],
        ];

        $this->postJson('/api/webhooks/payment/flutterwave', $payload, [
            'verif-hash' => 'flw_secret',
        ])->assertOk();

        $this->assertDatabaseHas('fiat_deposit_intents', [
            'reference' => $reference,
            'status' => 'credited',
        ]);

        $this->assertDatabaseHas('payment_intents', [
            'intent_id' => $paymentIntent->intent_id,
            'status' => 'completed',
            'provider_reference' => 'flw_fiat_card_tx_1',
        ]);

        $this->assertSame($reference, data_get($paymentIntent->fresh()->metadata, 'fiat_intent_reference'));

        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'transaction_id' => $reference,
            'user_id' => $user->id,
            'currency' => 'NGN',
        ]);
    }

    public function test_non_admin_cannot_settle_a_fiat_deposit_intent(): void
    {
        config()->set('swap.supported_fiat', ['NGN']);

        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $intent = FiatDepositIntent::query()->create([
            'reference' => 'FDP-LOCKED-TEST',
            'user_id' => $user->id,
            'method_id' => 'bank_transfer',
            'currency' => 'NGN',
            'gross_amount' => '10000.000000000000000000',
            'fee_amount' => '175.000000000000000000',
            'net_amount' => '9825.000000000000000000',
            'rate_bps' => '150',
            'fixed_fee' => '25.000000000000000000',
            'route_destination' => 'Funding',
            'status' => 'paid',
            'instructions' => ['type' => 'bank_transfer'],
            'disclosures' => ['Use the correct reference.'],
            'metadata' => [],
            'expires_at' => now()->addMinutes(30),
            'paid_at' => now(),
        ]);

        $this->actingAs($anotherUser)
            ->postJson("/api/wallet/deposit/fiat-intents/{$intent->reference}/settle")
            ->assertForbidden();

        $this->assertDatabaseMissing('transactions', [
            'reference' => $intent->reference,
        ]);
    }
}