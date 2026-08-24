<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\P2P\Services\P2PReconciliationService;
use App\Domain\P2P\Services\P2POperationalReadinessService;
use App\Models\LedgerTransaction;
use App\Models\P2PAd;
use App\Models\P2PPaymentMethod;
use App\Models\P2PTrade;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BalanceProjectionService;
use App\Services\LedgerService;
use App\Services\P2PService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase11P2PMarketplaceInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_creates_canonical_reservation_and_escrow_record(): void
    {
        [$seller, $buyer, $ad] = $this->sellerBuyerAndSellAd('50');

        $trade = app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->assertNotNull($trade->escrow_reservation_id);
        $this->assertDatabaseHas('reservations', [
            'reservation_id' => $trade->escrow_reservation_id,
            'purpose' => 'p2p_escrow',
            'status' => Reservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('p2p_escrows', [
            'trade_id' => $trade->id,
            'reservation_id' => $trade->escrow_reservation_id,
            'status' => 'reserved',
        ]);
        $this->assertDatabaseHas('p2p_order_events', [
            'trade_id' => $trade->id,
            'event_type' => 'order_created',
        ]);

        $projection = app(BalanceProjectionService::class)->byUserAccountAndAsset($seller->id, 'funding', 'USDT');
        $this->assertSame('20.000000000000000000', $projection['reserved']);
    }

    public function test_escrow_release_settles_once_through_ledger(): void
    {
        [$seller, $buyer, $ad] = $this->sellerBuyerAndSellAd('50');
        $trade = app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->actingAs($buyer)->postJson("/api/p2p/trades/{$trade->trade_uuid}/payment-sent")->assertOk();
        $this->actingAs($seller)->postJson("/api/p2p/trades/{$trade->trade_uuid}/release")->assertOk();
        $this->actingAs($seller)->postJson("/api/p2p/trades/{$trade->trade_uuid}/release")->assertStatus(422);

        $this->assertSame(1, LedgerTransaction::query()->where('reference', 'p2p:release:' . $trade->trade_uuid)->count());
        $this->assertDatabaseHas('reservations', [
            'reservation_id' => $trade->fresh()->escrow_reservation_id,
            'status' => Reservation::STATUS_CONSUMED,
        ]);

        $buyerBalance = app(LedgerService::class)->getBalance($buyer->id, 'USDT');
        $this->assertSame('20.000000000000000000', $buyerBalance);
    }

    public function test_cancellation_releases_reservation_and_restores_ad_inventory(): void
    {
        [$seller, $buyer, $ad] = $this->sellerBuyerAndSellAd('50');
        $trade = app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->actingAs($buyer)->postJson("/api/p2p/trades/{$trade->trade_uuid}/cancel")->assertOk();

        $this->assertDatabaseHas('reservations', [
            'reservation_id' => $trade->fresh()->escrow_reservation_id,
            'status' => Reservation::STATUS_RELEASED,
        ]);
        $this->assertSame('50.00000000', (string) $ad->fresh()->available_amount);
        $this->assertDatabaseHas('p2p_escrows', [
            'trade_id' => $trade->id,
            'status' => 'returned',
        ]);
    }

    public function test_two_buyers_cannot_oversubscribe_same_ad_inventory(): void
    {
        [, $buyer, $ad] = $this->sellerBuyerAndSellAd('20');
        $secondBuyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->actingAs($secondBuyer)->postJson("/api/p2p/ads/{$ad->id}/trades", [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ])->assertStatus(422);
    }

    public function test_payment_evidence_is_private_hashed_and_journaled(): void
    {
        Storage::fake('local');
        [$seller, $buyer, $ad] = $this->sellerBuyerAndSellAd('50');
        $trade = app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->actingAs($buyer)->postJson("/api/p2p/trades/{$trade->trade_uuid}/payment-proof", [
            'proof' => UploadedFile::fake()->create('receipt.pdf', 12, 'application/pdf'),
        ])->assertOk();

        $this->assertDatabaseHas('p2p_payment_evidence', [
            'trade_id' => $trade->id,
            'uploaded_by' => $buyer->id,
            'evidence_type' => 'payment_proof',
        ]);
        $this->assertNotNull(DB::table('p2p_payment_evidence')->where('trade_id', $trade->id)->value('sha256'));
        $this->assertDatabaseHas('p2p_order_events', [
            'trade_id' => $trade->id,
            'event_type' => 'payment_evidence_uploaded',
        ]);
    }

    public function test_reconciliation_and_admin_readiness_report_pass_for_clean_open_escrow(): void
    {
        [$seller, $buyer, $ad] = $this->sellerBuyerAndSellAd('50');
        app(P2PService::class)->openTrade($buyer, $ad->id, [
            'fiat_amount' => '30000',
            'payment_method' => 'Bank Transfer',
        ]);

        $report = app(P2PReconciliationService::class)->run();
        $this->assertSame('PASS', $report['status']);
        $this->assertSame(0, $report['difference_count']);

        $readiness = app(P2POperationalReadinessService::class)->check();
        $this->assertTrue($readiness['checks']['reconciliation']);
        $this->assertContains($readiness['status'], ['READY', 'DEGRADED']);
    }

    private function sellerBuyerAndSellAd(string $availableAmount): array
    {
        $seller = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        app(WalletService::class)->getWallet($seller->id, 'USDT')->update(['available_balance' => 100]);
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
            'available_amount' => $availableAmount,
            'payment_methods' => ['Bank Transfer'],
            'payment_time_limit_minutes' => 15,
            'status' => 'active',
        ]);

        return [$seller, $buyer, $ad];
    }

    private function createBankTransferPaymentMethod(User $user): P2PPaymentMethod
    {
        return P2PPaymentMethod::query()->create([
            'user_id' => $user->id,
            'method_type' => 'Bank Transfer',
            'fiat_currency' => 'NGN',
            'display_name' => 'NGN Main Account',
            'bank_name' => 'GTBank',
            'account_name' => $user->name,
            'account_number' => '0123456789',
            'is_default' => true,
            'is_enabled' => true,
        ]);
    }
}
