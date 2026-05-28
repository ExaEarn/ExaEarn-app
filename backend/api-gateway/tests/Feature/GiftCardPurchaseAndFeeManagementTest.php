<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GiftcardOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Services\GiftCard\GiftCardFeeCalculator;
use App\Services\GiftCard\GiftCardPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftCardPurchaseAndFeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private GiftCardFeeCalculator $feeCalculator;
    private GiftCardPurchaseService $purchaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feeCalculator = app(GiftCardFeeCalculator::class);
        $this->purchaseService = app(GiftCardPurchaseService::class);
    }

    /**
     * Test fee calculation for different brands and strategies.
     */
    public function test_fee_calculator_pass_to_user(): void
    {
        $feeBreakdown = $this->feeCalculator->calculateFees('amazon', 50.00, 'USD');

        $this->assertEquals(50.00, $feeBreakdown['card_value']);
        $this->assertGreaterThan(0, $feeBreakdown['api_fee']);
        $this->assertEquals('pass_to_user', $feeBreakdown['fee_breakdown']['strategy']);
        // User charge should be at least the API fee
        $this->assertGreaterThanOrEqual($feeBreakdown['api_fee'], $feeBreakdown['user_charge']);
        // Total cost should include card value + user charge
        $this->assertEquals(
            $feeBreakdown['card_value'] + $feeBreakdown['user_charge'],
            $feeBreakdown['total_cost_to_user']
        );
    }

    /**
     * Test fee calculation with split strategy.
     */
    public function test_fee_calculator_split_strategy(): void
    {
        $feeBreakdown = $this->feeCalculator->calculateFees('steam', 100.00, 'USD');

        $this->assertEquals('split', $feeBreakdown['fee_breakdown']['strategy']);
        $totalFee = $feeBreakdown['api_fee'] + $feeBreakdown['delivery_fee'];
        $userCharge = $feeBreakdown['user_charge'];
        
        // User should pay 50% of fees (based on config)
        $this->assertLessThan($totalFee, $userCharge);
        $this->assertGreaterThan(0, $userCharge);
    }

    /**
     * Test batch fee calculation.
     */
    public function test_batch_fee_calculation(): void
    {
        $orders = [
            ['brand' => 'amazon', 'card_value' => 50.00, 'currency' => 'USD'],
            ['brand' => 'steam', 'card_value' => 100.00, 'currency' => 'USD'],
            ['brand' => 'apple', 'card_value' => 75.00, 'currency' => 'USD'],
        ];

        $results = $this->feeCalculator->calculateBatchFees($orders);

        $this->assertCount(3, $results);
        $this->assertIsArray($results[0]);
        $this->assertTrue(isset($results[0]['total_cost_to_user']));
    }

    /**
     * Test complete gift card purchase flow.
     */
    public function test_purchase_gift_card_success(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        // Create wallet with sufficient balance
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '500.00',
            'locked_balance' => '0.00',
        ]);

        $order = $this->purchaseService->purchaseGiftCard(
            $user,
            'amazon',
            50.00,
            'test@example.com',
            'USD',
            'funding'
        );

        $this->assertNotNull($order->id);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('buy', $order->type);
        $this->assertArrayHasKey('brand', $order->metadata);
        $this->assertEquals('amazon', $order->metadata['brand']);
        
        // Verify wallet balance decreased
        $wallet = $user->wallets()->where('currency', 'USD')->first();
        $this->assertLessThan(500, $wallet->available_balance);
    }

    /**
     * Test purchase with insufficient balance fails.
     */
    public function test_purchase_with_insufficient_balance_fails(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '10.00',  // Not enough
            'locked_balance' => '0.00',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->purchaseService->purchaseGiftCard(
            $user,
            'amazon',
            50.00,
            'test@example.com',
            'USD'
        );
    }

    /**
     * Test purchase endpoint via HTTP.
     */
    public function test_purchase_endpoint_success(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '500.00',
            'locked_balance' => '0.00',
        ]);

        $response = $this->actingAs($user)->postJson('/api/giftcard/purchase', [
            'brand' => 'amazon',
            'card_value' => 50.00,
            'delivery_email' => 'user@example.com',
            'currency' => 'USD',
            'wallet_type' => 'funding',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'order_id',
                'reference',
                'status',
                'amount',
                'currency',
                'fees',
                'total_cost',
            ],
        ]);
    }

    /**
     * Test purchase endpoint with invalid brand fails.
     */
    public function test_purchase_endpoint_invalid_brand(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '500.00',
            'locked_balance' => '0.00',
        ]);

        $response = $this->actingAs($user)->postJson('/api/giftcard/purchase', [
            'brand' => 'invalid_brand',
            'card_value' => 50.00,
            'delivery_email' => 'user@example.com',
            'currency' => 'USD',
        ]);

        $response->assertUnprocessable();
    }

    /**
     * Test refund functionality.
     */
    public function test_refund_purchase(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $originalBalance = 500.00;
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => (string) $originalBalance,
            'locked_balance' => '0.00',
        ]);

        // Create order
        $order = $this->purchaseService->purchaseGiftCard(
            $user,
            'amazon',
            50.00,
            'test@example.com',
            'USD'
        );

        $balanceAfterPurchase = $user->wallets()->where('currency', 'USD')->first()->available_balance;
        $this->assertLessThan($originalBalance, $balanceAfterPurchase);

        // Refund
        $refundedOrder = $this->purchaseService->refundPurchase($order->id, 'user_request');
        
        $this->assertEquals('refunded', $refundedOrder->status);
        
        // Verify wallet restored
        $balanceAfterRefund = $user->wallets()->where('currency', 'USD')->first()->available_balance;
        $this->assertEquals($originalBalance, $balanceAfterRefund);
    }

    /**
     * Test ledger entries are created correctly.
     */
    public function test_ledger_entries_created(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '500.00',
            'locked_balance' => '0.00',
        ]);

        $order = $this->purchaseService->purchaseGiftCard(
            $user,
            'amazon',
            50.00,
            'test@example.com',
            'USD'
        );

        // Verify ledger entries were created
        $entries = \App\Models\LedgerEntry::query()
            ->where('reference', 'LIKE', "gcp:{$order->id}%")
            ->get();

        // Should have entries for: purchase, API fee, and profit (if any)
        $this->assertGreaterThanOrEqual(2, $entries->count());
    }

    /**
     * Test revenue summary calculation.
     */
    public function test_revenue_summary(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '10000.00',
            'locked_balance' => '0.00',
        ]);

        // Create multiple purchases
        for ($i = 0; $i < 3; $i++) {
            $this->purchaseService->purchaseGiftCard(
                $user,
                ['amazon', 'steam', 'apple'][$i],
                [50, 75, 100][$i],
                "test{$i}@example.com",
                'USD'
            );
        }

        $ledgerService = app(\App\Services\LedgerService::class);
        $summary = $ledgerService->getPlatformRevenueSummary('USD', now()->startOfDay(), now()->endOfDay());

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('summary', $summary);
        $this->assertGreaterThan(0, $summary['summary']['total_purchases']);
        $this->assertGreaterThan(0, $summary['summary']['transaction_count']);
    }

    /**
     * Test user purchase summary.
     */
    public function test_user_purchase_summary(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '10000.00',
            'locked_balance' => '0.00',
        ]);

        // Create multiple purchases
        for ($i = 0; $i < 2; $i++) {
            $this->purchaseService->purchaseGiftCard(
                $user,
                'amazon',
                50.00 + ($i * 10),
                "test{$i}@example.com",
                'USD'
            );
        }

        $summary = $this->purchaseService->getUserPurchaseSummary($user);

        $this->assertIsArray($summary);
        $this->assertEquals($user->id, $summary['user_id']);
        $this->assertEquals(2, $summary['purchase_count']);
        $this->assertGreaterThan(0, $summary['total_spent']);
    }

    /**
     * Test admin revenue report endpoint.
     */
    public function test_admin_revenue_report_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'available_balance' => '500.00',
            'locked_balance' => '0.00',
        ]);

        $this->purchaseService->purchaseGiftCard(
            $user,
            'amazon',
            50.00,
            'test@example.com',
            'USD'
        );

        $response = $this->actingAs($admin)->getJson('/api/giftcard/admin/revenue-summary', [
            'asset' => 'USD',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'period',
                'summary',
                'by_asset',
            ],
        ]);
    }

    /**
     * Test unknown provider throws exception.
     */
    public function test_unknown_provider_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown gift card provider');

        $this->feeCalculator->calculateFees('unknown_provider', 50.00, 'USD');
    }
}
