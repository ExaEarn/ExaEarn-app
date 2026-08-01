<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExpireP2PTradeJob;
use App\Jobs\ModerateP2PMessageJob;
use App\Models\P2PAd;
use App\Models\P2PPaymentMethod;
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

    public function test_user_can_create_payment_method_and_mask_response(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/p2p/payment-methods', [
            'method_type' => 'Bank Transfer',
            'fiat_currency' => 'NGN',
            'display_name' => 'GTBank Main Account',
            'bank_name' => 'GTBank',
            'account_name' => 'John Doe',
            'account_number' => '0123456789',
            'payment_note' => 'Use this account for NGN settlements.',
            'is_default' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.account_number', null)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_setting_default_payment_method_only_unsets_matching_fiat_and_method(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $ngnDefault = $this->createBankTransferPaymentMethod($user, 'NGN', '0123456789');
        $usdDefault = $this->createBankTransferPaymentMethod($user, 'USD', '9988776655');

        $this->actingAs($user)->postJson('/api/p2p/payment-methods', [
            'method_type' => 'Bank Transfer',
            'fiat_currency' => 'NGN',
            'display_name' => 'New NGN Default',
            'bank_name' => 'Access Bank',
            'account_name' => 'John Doe',
            'account_number' => '1111222233',
            'is_default' => true,
        ])->assertCreated();

        $this->assertFalse((bool) $ngnDefault->fresh()->is_default);
        $this->assertTrue((bool) $usdDefault->fresh()->is_default);
    }

    public function test_user_cannot_update_another_users_payment_method(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $intruder = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $method = $this->createBankTransferPaymentMethod($owner, 'NGN', '0123456789');

        $this->actingAs($intruder)->patchJson("/api/p2p/payment-methods/{$method->id}", [
            'display_name' => 'Hijacked',
        ])->assertNotFound();
    }

    public function test_user_can_create_sell_ad(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        app(WalletService::class)->getWallet($user->id, 'USDT')->update([
            'available_balance' => 500,
        ]);
        $this->createBankTransferPaymentMethod($user);

        $response = $this->actingAs($user)->postJson('/api/p2p/ads', [
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => 1500,
            'min_limit' => 10000,
            'max_limit' => 100000,
            'available_amount' => 250,
            'payment_methods' => ['Bank Transfer'],
            'payment_time_limit_minutes' => 15,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('p2p_ads', [
            'user_id' => $user->id,
            'type' => 'sell',
            'asset' => 'USDT',
        ]);
    }

    public function test_sell_ad_creation_rejects_insufficient_available_balance(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        app(WalletService::class)->getWallet($user->id, 'USDT')->update([
            'available_balance' => 10,
        ]);
        $this->createBankTransferPaymentMethod($user);

        $this->actingAs($user)->postJson('/api/p2p/ads', [
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => 1500,
            'min_limit' => 10000,
            'max_limit' => 100000,
            'available_amount' => 25,
            'payment_methods' => ['Bank Transfer'],
            'payment_time_limit_minutes' => 15,
        ])->assertStatus(422);
    }

    public function test_opening_trade_locks_seller_escrow_and_queues_expiry(): void
    {
        Queue::fake();

        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(WalletService::class)->getWallet($seller->id, 'USDT')->update([
            'available_balance' => 100,
        ]);
        $this->createBankTransferPaymentMethod($seller);

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
        $this->createBankTransferPaymentMethod($seller);

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
    }

    public function test_dispute_can_be_opened(): void
    {
        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(WalletService::class)->getWallet($seller->id, 'USDT')->update([
            'available_balance' => 100,
        ]);
        $this->createBankTransferPaymentMethod($seller);

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
    }

    public function test_trade_message_is_queued_for_moderation(): void
    {
        Queue::fake();

        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(WalletService::class)->getWallet($seller->id, 'USDT')->update([
            'available_balance' => 100,
        ]);
        $this->createBankTransferPaymentMethod($seller);

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

    private function createBankTransferPaymentMethod(User $user, string $fiatCurrency = 'NGN', string $accountNumber = '0123456789'): P2PPaymentMethod
    {
        return P2PPaymentMethod::query()->create([
            'user_id' => $user->id,
            'method_type' => 'Bank Transfer',
            'fiat_currency' => $fiatCurrency,
            'display_name' => sprintf('%s Main Account', $fiatCurrency),
            'bank_name' => 'GTBank',
            'account_name' => $user->name,
            'account_number' => $accountNumber,
            'is_default' => true,
            'is_enabled' => true,
        ]);
    }
}
