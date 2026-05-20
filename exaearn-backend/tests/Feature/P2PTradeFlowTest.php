<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExpireP2PTradeJob;
use App\Jobs\ModerateP2PMessageJob;
use App\Models\P2PAd;
use App\Models\User;
use App\Services\P2PService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class P2PTradeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_sell_ad(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/p2p/ads', [
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => 1500,
            'min_limit' => 10000,
            'max_limit' => 100000,
            'available_amount' => 250,
            'payment_methods' => ['Bank Transfer', 'Opay'],
            'payment_time_limit_minutes' => 15,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('p2p_ads', [
            'user_id' => $user->id,
            'type' => 'sell',
            'asset' => 'USDT',
        ]);
    }

    public function test_opening_trade_locks_seller_escrow_and_queues_expiry(): void
    {
        Queue::fake();

        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(WalletService::class)->getWallet($seller->id, 'USDT')->update([
            'available_balance' => 100,
        ]);

        $ad = P2PAd::query()->create([
            'ad_uuid' => (string) Str::uuid(),
            'user_id' => $seller->id,
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => 1500,
            'min_limit' => 15000,
            'max_limit' => 100000,
            'available_amount' => 50,
            'payment_methods' => ['Bank Transfer'],
            'payment_time_limit_minutes' => 15,
            'status' => 'active',
        ]);

        $response = $this->actingAs($buyer)->postJson("/api/p2p/ads/{$ad->id}/trades", [
            'fiat_amount' => 30000,
            'payment_method' => 'Bank Transfer',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('p2p_trades', [
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
        ]);
        Queue::assertPushed(ExpireP2PTradeJob::class);
    }

    public function test_buyer_payment_and_seller_release_complete_trade(): void
    {
        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(WalletService::class)->getWallet($seller->id, 'USDT')->update([
            'available_balance' => 100,
        ]);
        app(WalletService::class)->getWallet($buyer->id, 'USDT');

        $ad = app(P2PService::class)->createAd($seller, [
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => '1500',
            'min_limit' => '15000',
            'max_limit' => '100000',
            'available_amount' => '100',
            'payment_methods' => ['Bank Transfer'],
            'payment_time_limit_minutes' => 15,
        ]);

        $trade = app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->actingAs($buyer)->postJson("/api/p2p/trades/{$trade->trade_uuid}/payment-sent")->assertOk();
        $this->actingAs($seller)->postJson("/api/p2p/trades/{$trade->trade_uuid}/release")->assertOk();

        $this->assertDatabaseHas('p2p_trades', [
            'id' => $trade->id,
            'status' => 'released',
        ]);
    }

    public function test_dispute_can_be_opened(): void
    {
        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(WalletService::class)->getWallet($seller->id, 'USDT')->update([
            'available_balance' => 100,
        ]);

        $ad = app(P2PService::class)->createAd($seller, [
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => '1500',
            'min_limit' => '15000',
            'max_limit' => '100000',
            'available_amount' => '100',
            'payment_methods' => ['Bank Transfer'],
            'payment_time_limit_minutes' => 15,
        ]);

        $trade = app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->actingAs($buyer)->postJson("/api/p2p/trades/{$trade->trade_uuid}/disputes", [
            'reason' => 'Seller says payment not received.',
            'evidence' => ['proof.png'],
        ])->assertCreated();

        $this->assertDatabaseHas('p2p_disputes', [
            'trade_id' => $trade->id,
            'status' => 'open',
        ]);
    }

    public function test_trade_message_is_queued_for_moderation(): void
    {
        Queue::fake();

        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(WalletService::class)->getWallet($seller->id, 'USDT')->update([
            'available_balance' => 100,
        ]);

        $ad = app(P2PService::class)->createAd($seller, [
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => '1500',
            'min_limit' => '15000',
            'max_limit' => '100000',
            'available_amount' => '100',
            'payment_methods' => ['Bank Transfer'],
            'payment_time_limit_minutes' => 15,
        ]);

        $trade = app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->actingAs($buyer)->postJson("/api/p2p/trades/{$trade->trade_uuid}/messages", [
            'message' => 'Please confirm once you receive payment.',
        ])->assertCreated();

        Queue::assertPushed(ModerateP2PMessageJob::class);
    }
}
