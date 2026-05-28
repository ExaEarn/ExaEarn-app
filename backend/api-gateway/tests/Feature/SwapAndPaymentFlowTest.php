<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExecuteSwapJob;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SwapAndPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_expiry_blocks_execution(): void
    {
        config()->set('swap.quote_ttl_seconds', 1);
        Http::fake([
            'https://open.er-api.com/*' => Http::response([
                'rates' => ['USD' => '0.00066'],
            ], 200),
        ]);

        $user = User::factory()->create();
        Wallet::query()->updateOrCreate([
            'user_id' => $user->id,
            'currency' => 'NGN',
        ], [
            'available_balance' => '1000000',
            'locked_balance' => '0',
        ]);

        $quoteResponse = $this->actingAs($user)->postJson('/api/swap/quote', [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 1000,
        ]);
        $quoteResponse->assertCreated();

        sleep(2);

        $execute = $this->actingAs($user)->postJson('/api/swap/execute', [
            'quote_id' => $quoteResponse->json('quote_id'),
        ]);

        $execute->assertStatus(422)
            ->assertJson(['message' => 'Quote expired.']);
    }

    public function test_duplicate_execute_is_idempotent(): void
    {
        config()->set('swap.quote_ttl_seconds', 30);
        Http::fake([
            'https://open.er-api.com/*' => Http::response([
                'rates' => ['USD' => '0.00066'],
            ], 200),
        ]);

        Queue::fake();

        $user = User::factory()->create();
        Wallet::query()->updateOrCreate([
            'user_id' => $user->id,
            'currency' => 'NGN',
        ], [
            'available_balance' => '1000000',
            'locked_balance' => '0',
        ]);

        $quote = $this->actingAs($user)->postJson('/api/swap/quote', [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 5000,
        ])->json();

        $idempotency = (string) Str::uuid();

        $first = $this->actingAs($user)
            ->withHeader('X-Idempotency-Key', $idempotency)
            ->postJson('/api/swap/execute', ['quote_id' => $quote['quote_id']]);

        $second = $this->actingAs($user)
            ->withHeader('X-Idempotency-Key', $idempotency)
            ->postJson('/api/swap/execute', ['quote_id' => $quote['quote_id']]);

        $first->assertStatus(202);
        $second->assertStatus(202);

        $this->assertSame(
            $first->json('data.swap_id'),
            $second->json('data.swap_id')
        );

        Queue::assertPushed(ExecuteSwapJob::class, 1);
        $this->assertDatabaseCount('swaps', 1);
    }

    public function test_flutterwave_and_nomba_webhook_verification_and_replay_protection(): void
    {
        config()->set('services.flutterwave.webhook_secret', 'flw_secret');
        config()->set('services.nomba.webhook_secret', 'nomba_secret');

        $user = User::factory()->create();

        $flutterIntent = PaymentIntent::create([
            'intent_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider' => 'flutterwave',
            'currency' => 'NGN',
            'amount' => '1000',
            'status' => 'pending',
        ]);

        $flutterPayload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'flw_tx_1',
                'tx_ref' => $flutterIntent->intent_id,
                'amount' => 1000,
                'status' => 'successful',
            ],
        ];

        $this->postJson('/api/webhooks/payment/flutterwave', $flutterPayload, [
            'verif-hash' => 'flw_secret',
        ])->assertOk();

        $this->postJson('/api/webhooks/payment/flutterwave', $flutterPayload, [
            'verif-hash' => 'flw_secret',
        ])->assertOk();

        $nombaIntent = PaymentIntent::create([
            'intent_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider' => 'nomba',
            'currency' => 'NGN',
            'amount' => '500',
            'status' => 'pending',
        ]);

        $timestamp = (string) now()->timestamp;
        $nombaPayload = [
            'event' => 'payment.successful',
            'data' => [
                'transaction' => [
                    'merchant_ref' => $nombaIntent->intent_id,
                    'reference' => 'nomba_provider_ref_1',
                    'amount' => '500',
                ],
            ],
        ];

        $nombaMessage = $timestamp . 'payment.successful' . '500' . $nombaIntent->intent_id;
        $nombaSig = hash_hmac('sha256', $nombaMessage, 'nomba_secret');

        $this->postJson('/api/webhooks/payment/nomba', $nombaPayload, [
            'nomba-signature' => $nombaSig,
            'x-nomba-timestamp' => $timestamp,
        ])->assertOk();

        $secondNombaIntent = PaymentIntent::create([
            'intent_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider' => 'nomba',
            'currency' => 'NGN',
            'amount' => '500',
            'status' => 'pending',
        ]);

        $replayPayload = [
            'event' => 'payment.successful',
            'data' => [
                'transaction' => [
                    'merchant_ref' => $secondNombaIntent->intent_id,
                    'reference' => 'nomba_provider_ref_1',
                    'amount' => '500',
                ],
            ],
        ];

        $replayMessage = $timestamp . 'payment.successful' . '500' . $secondNombaIntent->intent_id;
        $replaySig = hash_hmac('sha256', $replayMessage, 'nomba_secret');

        $this->postJson('/api/webhooks/payment/nomba', $replayPayload, [
            'nomba-signature' => $replaySig,
            'x-nomba-timestamp' => $timestamp,
        ])->assertStatus(422)->assertJson([
            'message' => 'Webhook replay detected.',
        ]);

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_payment_provider_auto_routing_prefers_nomba_for_supported_countries(): void
    {
        config()->set('payments.nomba_supported_countries', ['NG', 'GH']);

        $user = User::factory()->create();

        $ng = $this->actingAs($user)->postJson('/api/payments/initiate', [
            'country' => 'NG',
            'currency' => 'NGN',
            'amount' => 1000,
        ]);
        $ng->assertCreated();
        $this->assertSame('nomba', $ng->json('routing.provider'));

        $za = $this->actingAs($user)->postJson('/api/payments/initiate', [
            'country' => 'ZA',
            'currency' => 'USD',
            'amount' => 100,
        ]);
        $za->assertCreated();
        $this->assertSame('flutterwave', $za->json('routing.provider'));

        $override = $this->actingAs($user)->postJson('/api/payments/initiate', [
            'country' => 'NG',
            'provider' => 'flutterwave',
            'currency' => 'NGN',
            'amount' => 300,
        ]);
        $override->assertCreated();
        $this->assertSame('flutterwave', $override->json('routing.provider'));
    }
}
