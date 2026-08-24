<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GiftcardOrder;
use App\Models\LedgerTransaction;
use App\Models\PricingRule;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountClosureSafetyService;
use App\Services\GiftCard\GiftCardAdminCenterService;
use App\Services\GiftCard\GiftCardPricingEngine;
use App\Services\GiftCard\GiftCardPurchaseService;
use App\Services\GiftCard\GiftCardReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GiftCardProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_unknown_keeps_order_under_review_with_active_reservation(): void
    {
        $user = $this->fundedUser('USD', '500.00');

        $order = app(GiftCardPurchaseService::class)->purchaseGiftCard(
            $user,
            'amazon',
            '50.00',
            'buyer@example.com',
            'USD',
            'funding',
            ['provider_scenario' => 'PROVIDER_UNKNOWN'],
        );

        $this->assertSame('provider_unknown', $order->status);
        $this->assertNotEmpty($order->metadata['provider_reference']);

        $reservation = Reservation::query()
            ->where('reservation_id', $order->metadata['reservation_id'])
            ->firstOrFail();

        $this->assertSame(Reservation::STATUS_ACTIVE, $reservation->status);
        $this->assertFalse(LedgerTransaction::query()->where('reference', 'giftcard_purchase:'.$order->id)->exists());
    }

    public function test_failed_provider_does_not_create_a_completed_order_or_settlement(): void
    {
        $user = $this->fundedUser('USD', '500.00');

        try {
            app(GiftCardPurchaseService::class)->purchaseGiftCard(
                $user,
                'amazon',
                '50.00',
                'buyer@example.com',
                'USD',
                'funding',
                ['provider_scenario' => 'FAILED'],
            );
            $this->fail('Expected provider failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SANDBOX_PROVIDER_DECLINE', $exception->getMessage());
        }

        $this->assertSame(0, GiftcardOrder::query()->where('status', 'completed')->count());
        $this->assertSame(0, LedgerTransaction::query()->where('transaction_type', 'giftcard_purchase')->count());
    }

    public function test_refund_is_idempotent_and_uses_canonical_ledger(): void
    {
        $user = $this->fundedUser('USD', '500.00');
        $service = app(GiftCardPurchaseService::class);

        $order = $service->purchaseGiftCard($user, 'amazon', '50.00', 'buyer@example.com', 'USD');

        $firstRefund = $service->refundPurchase($order->id, 'customer_request');
        $secondRefund = $service->refundPurchase($order->id, 'customer_retry');

        $this->assertSame('refunded', $firstRefund->status);
        $this->assertSame('refunded', $secondRefund->status);
        $this->assertSame(1, LedgerTransaction::query()->where('reference', 'giftcard_refund:'.$order->id)->count());
    }

    public function test_reconciliation_passes_for_completed_canonical_purchase(): void
    {
        $user = $this->fundedUser('USD', '500.00');

        app(GiftCardPurchaseService::class)->purchaseGiftCard($user, 'amazon', '50.00', 'buyer@example.com', 'USD');

        $result = app(GiftCardReconciliationService::class)->run('USD');

        $this->assertSame('PASS', $result['status']);
        $this->assertSame([], $result['findings']);
    }

    public function test_giftcard_pricing_uses_central_pricing_policy_engine(): void
    {
        \App\Models\GiftCardRate::query()->create([
            'brand' => 'amazon',
            'rate' => '0.85',
            'currency' => 'USD',
            'min_value' => 10,
            'max_value' => 500,
            'active' => true,
            'metadata' => [],
        ]);

        $pricing = app(GiftCardPricingEngine::class)->calculateTotalPrice('amazon', '100', 1, 'USD');

        $this->assertSame('PRICING_ENGINE', data_get($pricing, 'pricing_snapshot.markup.source'));
        $this->assertNotNull(data_get($pricing, 'pricing_snapshot.markup.pricing_rule_id'));
        $this->assertTrue(PricingRule::query()->where('product', 'GIFTCARD')->where('operation', 'BUY_MARKUP')->exists());
        $this->assertTrue(PricingRule::query()->where('product', 'GIFTCARD')->where('operation', 'PLATFORM_FEE')->exists());
    }

    public function test_admin_giftcard_center_exposes_required_sections(): void
    {
        $center = app(GiftCardAdminCenterService::class)->dashboard('USD');

        foreach ([
            'overview',
            'buy_orders',
            'sell_submissions',
            'inventory',
            'brands_products',
            'rates',
            'pricing',
            'providers',
            'delivery',
            'treasury',
            'reconciliation',
            'fraud',
            'refunds',
            'reports',
            'audit',
        ] as $section) {
            $this->assertArrayHasKey($section, $center['sections']);
        }
    }

    public function test_account_closure_is_blocked_by_unresolved_giftcard_state(): void
    {
        $user = $this->fundedUser('USD', '500.00');

        GiftcardOrder::query()->create([
            'user_id' => $user->id,
            'type' => 'buy',
            'amount' => '25.00',
            'currency' => 'USD',
            'status' => 'provider_unknown',
            'reference' => 'GIFT-CLOSURE-TEST',
            'metadata' => ['brand' => 'amazon'],
        ]);

        $readiness = app(AccountClosureSafetyService::class)->readiness($user->id);

        $this->assertFalse($readiness['can_close']);
        $this->assertSame('BLOCKED', $readiness['status']);
        $this->assertSame('giftcard_order', $readiness['blockers'][0]['type']);
    }

    private function fundedUser(string $currency, string $amount): User
    {
        $user = User::factory()->create(['role' => 'user']);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => $currency,
            'available_balance' => $amount,
            'locked_balance' => '0.00',
        ]);

        return $user;
    }
}
