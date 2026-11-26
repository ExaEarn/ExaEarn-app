<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class UnifiedTransactionHistorySourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_history_cursor_is_user_scoped_and_stable(): void
    {
        $userId = (int) User::factory()->create()->id;
        $otherUserId = (int) User::factory()->create()->id;
        $timestamp = now()->subDay();
        $ownerCard = $this->createCard($userId);
        $this->createCardTransaction($userId, $ownerCard, 'card-owner-a', $timestamp);
        $this->createCardTransaction($userId, $ownerCard, 'card-owner-b', $timestamp);
        $otherCard = $this->createCard($otherUserId);
        $this->createCardTransaction($otherUserId, $otherCard, 'card-other', $timestamp);

        Sanctum::actingAs(User::query()->findOrFail($userId));
        $path = '/api/cards/transactions/mine?per_page=1';
        $first = $this->getJson($path)->assertOk()
            ->assertJsonPath('data.0.provider_reference', 'card-owner-b')
            ->assertJsonPath('data.0.network', 'visa')
            ->assertJsonMissing(['provider_reference' => 'card-other']);
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotEmpty($cursor);

        $this->getJson($path . '&cursor=' . urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.0.provider_reference', 'card-owner-a')
            ->assertJsonMissing(['provider_reference' => 'card-other']);
    }

    public function test_staking_history_cursor_is_user_scoped_and_uses_asset_precision(): void
    {
        $userId = (int) User::factory()->create()->id;
        $otherUserId = (int) User::factory()->create()->id;
        $assetId = (int) DB::table('staking_assets')->insertGetId([
            'symbol' => 'EXA',
            'network' => 'testnet',
            'provider' => 'test-provider',
            'staking_type' => 'native',
            'amount_precision' => 8,
            'reward_precision' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $timestamp = now()->subDay();
        $ownerA = (string) Str::uuid();
        $ownerB = (string) Str::uuid();
        $otherPublicId = (string) Str::uuid();
        $this->createStakingTransaction($userId, $assetId, $ownerA, $timestamp);
        $this->createStakingTransaction($userId, $assetId, $ownerB, $timestamp);
        $this->createStakingTransaction($otherUserId, $assetId, $otherPublicId, $timestamp);

        Sanctum::actingAs(User::query()->findOrFail($userId));
        $path = '/api/v1/staking/transactions?cursor_mode=1&per_page=1';
        $first = $this->getJson($path)->assertOk()
            ->assertJsonPath('data.0.transaction_id', $ownerB)
            ->assertJsonPath('data.0.network', 'testnet')
            ->assertJsonPath('data.0.precision', 8)
            ->assertJsonMissing(['transaction_id' => $otherPublicId]);
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotEmpty($cursor);

        $this->getJson($path . '&cursor=' . urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.0.transaction_id', $ownerA)
            ->assertJsonMissing(['transaction_id' => $otherPublicId]);
    }

    public function test_p2p_history_cursor_is_user_scoped_and_stable(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $thirdUser = User::factory()->create();
        $adId = (int) DB::table('p2p_ads')->insertGetId([
            'ad_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'sell',
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'price' => '1500',
            'min_limit' => '1000',
            'max_limit' => '100000',
            'available_amount' => '100',
            'payment_methods' => json_encode(['Bank transfer']),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $timestamp = now()->subDay();
        $ownerA = (string) Str::uuid();
        $ownerB = (string) Str::uuid();
        $otherTrade = (string) Str::uuid();
        $this->createP2PTrade($adId, $user->id, $otherUser->id, $ownerA, $timestamp);
        $this->createP2PTrade($adId, $thirdUser->id, $user->id, $ownerB, $timestamp);
        $this->createP2PTrade($adId, $otherUser->id, $thirdUser->id, $otherTrade, $timestamp);

        Sanctum::actingAs($user);
        $path = '/api/p2p/trades/mine?cursor_mode=1&per_page=1';
        $first = $this->getJson($path)->assertOk()
            ->assertJsonPath('data.0.trade_uuid', $ownerB)
            ->assertJsonMissing(['trade_uuid' => $otherTrade]);
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotEmpty($cursor);

        $this->getJson($path . '&cursor=' . urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.0.trade_uuid', $ownerA)
            ->assertJsonMissing(['trade_uuid' => $otherTrade]);
    }

    private function createCard(int $userId): int
    {
        $customerId = (int) DB::table('card_customers')->insertGetId([
            'customer_uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'provider' => 'history-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('cards')->insertGetId([
            'card_uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'card_customer_id' => $customerId,
            'provider' => 'history-test',
            'card_product' => 'virtual',
            'type' => 'virtual',
            'currency' => 'USD',
            'network' => 'visa',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCardTransaction(int $userId, int $cardId, string $reference, $createdAt): void
    {
        DB::table('card_transactions')->insert([
            'transaction_uuid' => (string) Str::uuid(),
            'card_id' => $cardId,
            'user_id' => $userId,
            'provider' => 'history-test',
            'type' => 'PURCHASE',
            'provider_reference' => $reference,
            'merchant' => 'Test merchant',
            'transaction_currency' => 'USD',
            'transaction_amount' => '5.000000000000000000',
            'billing_currency' => 'USD',
            'billing_amount' => '5.000000000000000000',
            'fee' => '0',
            'provider_cost' => '0',
            'fx_rate' => '1',
            'status' => 'SETTLED',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createStakingTransaction(int $userId, int $assetId, string $reference, $createdAt): void
    {
        DB::table('staking_transactions')->insert([
            'public_id' => $reference,
            'user_id' => $userId,
            'staking_asset_id' => $assetId,
            'transaction_type' => 'reward',
            'amount' => '2.500000000000000000',
            'fee_amount' => '0',
            'net_amount' => '2.500000000000000000',
            'status' => 'confirmed',
            'idempotency_key' => $reference,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createP2PTrade(int $adId, int $buyerId, int $sellerId, string $tradeUuid, $createdAt): void
    {
        DB::table('p2p_trades')->insert([
            'trade_uuid' => $tradeUuid,
            'ad_id' => $adId,
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'escrow_holder_user_id' => $sellerId,
            'asset' => 'USDT',
            'fiat_currency' => 'NGN',
            'crypto_amount' => '5',
            'fiat_amount' => '7500',
            'price' => '1500',
            'payment_method' => 'Bank transfer',
            'payment_window_minutes' => 15,
            'payment_deadline' => $createdAt->copy()->addMinutes(15),
            'status' => 'completed',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
