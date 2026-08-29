<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AffiliateClawback;
use App\Models\AffiliateCommissionEvent;
use App\Models\AffiliatePayout;
use App\Models\ExaPointTransaction;
use App\Models\Referral;
use App\Models\User;
use App\Services\AffiliateCommissionService;
use App\Services\AccountClosureSafetyService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateCommissionProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_routes_match_web_contract(): void
    {
        [$referrer] = $this->usersWithReferral();

        $this->actingAs($referrer)->getJson('/api/affiliate/overview')
            ->assertOk()
            ->assertJsonStructure(['data' => ['profile', 'stats', 'funnel']]);

        $this->actingAs($referrer)->getJson('/api/affiliate/tools')
            ->assertOk()
            ->assertJsonPath('data.disclosure', 'ExaPoints are the active reward instrument. ExaToken distribution is disabled.');

        $this->actingAs($referrer)->getJson('/api/affiliate/payouts')
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary', 'items']]);
    }

    public function test_settled_commissionable_event_creates_pending_hold_once(): void
    {
        [$referrer, $referred] = $this->usersWithReferral();

        /** @var AffiliateCommissionService $service */
        $service = app(AffiliateCommissionService::class);
        $first = $service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-1', '100', [
            'settlement_status' => 'SETTLED',
        ]);
        $second = $service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-1', '100', [
            'settlement_status' => 'SETTLED',
        ]);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertDatabaseCount('affiliate_commission_events', 1);
        $this->assertDatabaseHas('affiliate_commission_events', [
            'affiliate_user_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'status' => 'PENDING',
            'reward_asset' => 'EXAPOINT',
        ]);
    }

    public function test_non_commissionable_unsettled_and_sandbox_events_do_not_create_commission(): void
    {
        [, $referred] = $this->usersWithReferral();

        /** @var AffiliateCommissionService $service */
        $service = app(AffiliateCommissionService::class);
        $this->assertNull($service->recordSettledEvent($referred->id, 'SPOT', 'FEE', 'spot-fee-1', '10', ['settlement_status' => 'SETTLED']));
        $this->assertNull($service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-pending', '100', ['settlement_status' => 'PENDING']));
        $this->assertNull($service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-sandbox', '100', ['environment' => 'sandbox']));

        $this->assertDatabaseCount('affiliate_commission_events', 0);
    }

    public function test_hold_release_and_exapoint_payout_are_idempotent(): void
    {
        [$referrer, $referred] = $this->usersWithReferral();

        /** @var AffiliateCommissionService $service */
        $service = app(AffiliateCommissionService::class);
        $commission = $service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-pay', '100', ['settlement_status' => 'SETTLED']);
        $commission->forceFill(['hold_until' => now()->subMinute()])->save();

        $this->assertSame(1, $service->releaseMatureHolds($referrer->id));
        $this->assertSame(0, $service->releaseMatureHolds($referrer->id));

        $amount = (string) AffiliateCommissionEvent::query()->where('affiliate_user_id', $referrer->id)->value('commission_amount');
        $first = $service->requestPayout($referrer, $amount, 'EXAPOINT', 'payout-key-1');
        $second = $service->requestPayout($referrer, $amount, 'EXAPOINT', 'payout-key-1');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('affiliate_payouts', 1);
        $this->assertSame(1, ExaPointTransaction::query()->where('reference', 'affiliate:payout:' . $first->payout_uuid)->count());
        $this->assertSame('PAID', AffiliateCommissionEvent::query()->first()->status);
    }

    public function test_reversal_before_payout_reverses_commission_and_after_payout_creates_pending_clawback(): void
    {
        [$referrer, $referred] = $this->usersWithReferral();

        /** @var AffiliateCommissionService $service */
        $service = app(AffiliateCommissionService::class);
        $pending = $service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-reverse', '100', ['settlement_status' => 'SETTLED']);
        $prePayoutClawback = $service->reverse('EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-reverse', 'refund-1', 'REFUND');

        $this->assertSame('APPLIED', $prePayoutClawback?->status);
        $this->assertSame('REVERSED', $pending?->fresh()->status);

        $paid = $service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-claw', '100', ['settlement_status' => 'SETTLED']);
        $paid->forceFill(['hold_until' => now()->subMinute()])->save();
        $service->releaseMatureHolds($referrer->id);
        $service->requestPayout($referrer, (string) $paid->commission_amount, 'EXAPOINT', 'payout-key-2');
        $postPayoutClawback = $service->reverse('EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-claw', 'chargeback-1', 'CHARGEBACK');
        $duplicate = $service->reverse('EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-claw', 'chargeback-1', 'CHARGEBACK');

        $this->assertSame('PENDING', $postPayoutClawback?->status);
        $this->assertSame($postPayoutClawback?->id, $duplicate?->id);
        $this->assertSame(2, AffiliateClawback::query()->count());
    }

    public function test_referral_service_routes_subscription_purchase_to_affiliate_commission(): void
    {
        [, $referred] = $this->usersWithReferral();

        /** @var ReferralService $referrals */
        $referrals = app(ReferralService::class);
        $referrals->processQualifiedActivity($referred->id, 'subscription_purchase', [
            'subscription_id' => 'exaai-subscription-42',
            'amount' => '50',
            'settlement_status' => 'SETTLED',
        ]);

        $this->assertDatabaseHas('affiliate_commission_events', [
            'product' => 'EXAAI',
            'event_type' => 'SUBSCRIPTION_PURCHASE',
            'source_reference' => 'exaai-subscription-42',
        ]);
    }

    public function test_account_closure_is_blocked_by_unresolved_affiliate_obligations(): void
    {
        [$referrer, $referred] = $this->usersWithReferral();

        /** @var AffiliateCommissionService $service */
        $service = app(AffiliateCommissionService::class);
        $service->recordSettledEvent($referred->id, 'EXAAI', 'SUBSCRIPTION_PURCHASE', 'sub-close-block', '100', ['settlement_status' => 'SETTLED']);

        /** @var AccountClosureSafetyService $closure */
        $closure = app(AccountClosureSafetyService::class);
        $readiness = $closure->readiness($referrer->id);

        $this->assertFalse($readiness['can_close']);
        $this->assertSame('unresolved_affiliate_commissions', $readiness['blockers'][0]['reason']);
    }

    private function usersWithReferral(): array
    {
        $referrer = User::factory()->create(['email_verified_at' => now()]);
        $referred = User::factory()->create(['email_verified_at' => now()]);

        /** @var ReferralService $referrals */
        $referrals = app(ReferralService::class);
        $referrals->ensureReferralCode($referrer);
        $referrals->bindReferral($referred, (string) $referrer->fresh()->referral_code);

        $this->assertSame(1, Referral::query()->count());

        return [$referrer->fresh(), $referred->fresh()];
    }
}
