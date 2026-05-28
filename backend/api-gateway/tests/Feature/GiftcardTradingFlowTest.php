<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AdminApprovalJob;
use App\Jobs\FraudAnalysisJob;
use App\Jobs\ProcessGiftcardBuyJob;
use App\Jobs\ProcessGiftcardSellJob;
use App\Models\Giftcard;
use App\Models\GiftcardRate;
use App\Models\GiftCardInventory;
use App\Models\GiftcardOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BlockchainService;
use App\Services\GiftcardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GiftcardTradingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sell_order_is_queued_for_fraud_analysis(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->postJson('/api/giftcard/sell', [
            'brand' => 'Amazon',
            'card_value' => 25,
            'currency' => 'USD',
            'card_code' => 'AMZN-CODE-123456',
        ]);

        $response->assertAccepted();
        $this->assertDatabaseHas('giftcard_orders', [
            'user_id' => $user->id,
            'type' => 'sell',
            'status' => 'pending_analysis',
        ]);
        Queue::assertPushed(FraudAnalysisJob::class);
    }

    public function test_duplicate_sell_order_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Giftcard::query()->create([
            'owner_user_id' => $user->id,
            'card_type' => 'Amazon',
            'provider' => 'amazon',
            'amount' => 25,
            'currency' => 'USD',
            'encrypted_code' => encrypt('AMZN-CODE-123456'),
            'card_hash' => hash('sha256', 'AMZN-CODE-123456'),
            'status' => 'available',
            'verified_source' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/giftcard/sell', [
            'brand' => 'Amazon',
            'card_value' => 25,
            'currency' => 'USD',
            'card_code' => 'AMZN-CODE-123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_low_risk_sell_order_auto_processes(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        /** @var BlockchainService|\Mockery\MockInterface $blockchain */
        $blockchain = $this->mock(BlockchainService::class);
        $blockchain->shouldReceive('analyzeGiftcardFraud')->once()->andReturn([
            'risk_score' => 10,
            'risk_level' => 'LOW',
            'reason' => ['Trusted user'],
        ]);

        $order = app(GiftcardService::class)->submitSellOrder($user, [
            'card_type' => 'Apple',
            'provider' => 'apple',
            'amount' => 20,
            'card_code' => 'APPLE-CODE-123456',
        ]);

        Queue::assertPushed(FraudAnalysisJob::class);

        app(GiftcardService::class)->analyzeOrderRisk($order->id);

        Queue::assertPushed(ProcessGiftcardSellJob::class);
    }

    public function test_high_risk_buy_order_goes_to_admin_review(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);

        // Create rate
        GiftcardRate::query()->create([
            'brand' => 'steam',
            'rate' => 0.85,
            'currency' => 'USD',
            'min_value' => 10,
            'max_value' => 200,
            'active' => true,
            'metadata' => [],
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '500.00',
            'locked_balance' => '0.00',
        ]);

        $inventory = GiftCardInventory::query()->create([
            'brand' => 'steam',
            'card_value' => 150,
            'currency' => 'USD',
            'encrypted_card_code' => encrypt('STEAM-CODE-123456'),
            'encrypted_card_pin' => null,
            'available' => true,
            'metadata' => [],
        ]);

        $blockchain = $this->mock(BlockchainService::class);
        $blockchain->shouldReceive('analyzeGiftcardFraud')->once()->andReturn([
            'risk_score' => 82,
            'risk_level' => 'HIGH',
            'reason' => ['High amount', 'New account'],
        ]);

        $order = app(GiftcardService::class)->submitBuyOrder($user, [
            'brand' => 'steam',
            'card_value' => 150,
            'currency' => 'USD',
            'quantity' => 1,
        ]);

        app(GiftcardService::class)->analyzeOrderRisk($order->id);

        $this->assertDatabaseHas('giftcard_orders', [
            'id' => $order->id,
            'status' => 'flagged',
            'requires_admin_review' => true,
        ]);
        Queue::assertPushed(AdminApprovalJob::class);
    }
}
