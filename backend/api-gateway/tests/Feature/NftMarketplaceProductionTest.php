<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Nft;
use App\Models\NftChainTransaction;
use App\Models\NftListing;
use App\Models\NftSale;
use App\Models\Reservation;
use App\Models\User;
use App\Services\NftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NftMarketplaceProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_nft_mint_persists_pending_chain_state_without_faking_confirmation(): void
    {
        $creator = User::factory()->create();

        $response = $this->actingAs($creator)->withHeader('Idempotency-Key', 'mint-1')->postJson('/api/nft/mint', [
            'utility_type' => 'creator_access',
            'name' => 'Founder Pass',
            'wallet_address' => '0xcreator',
            'collection_name' => 'Founder Collection',
            'description' => 'Access NFT.',
            'image' => 'https://example.com/founder.png',
        ])->assertCreated()->assertJsonPath('data.mint_status', 'PENDING');

        $this->assertDatabaseHas('nft_chain_transactions', [
            'nft_id' => $response->json('data.id'),
            'operation' => 'MINT',
            'status' => 'PENDING_PROVIDER_CONFIGURATION',
        ]);
    }

    public function test_fixed_price_purchase_uses_canonical_ledger_fees_royalties_and_updates_ownership_once(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        Account::query()->create(['user_id' => $buyer->id, 'account_type' => 'funding', 'asset' => 'EXA', 'balance' => '100.000000000000000000']);

        $nft = $this->actingAs($seller)->postJson('/api/nft/mint', [
            'utility_type' => 'creator_access',
            'name' => 'Tradeable NFT',
            'wallet_address' => '0xseller',
            'collection_name' => 'Tradeable Collection',
            'royalty_percentage' => 500,
        ])->assertCreated()->json('data');
        Nft::query()->whereKey($nft['id'])->update(['status' => 'active', 'mint_status' => 'CONFIRMED', 'moderation_status' => 'APPROVED']);

        $listing = $this->actingAs($seller)->withHeader('Idempotency-Key', 'listing-1')->postJson("/api/nft/assets/{$nft['id']}/listings", [
            'wallet_address' => '0xseller',
            'price_exa' => '20',
            'settlement_asset' => 'EXA',
        ])->assertCreated()->assertJsonPath('data.status', 'active')->json('data');

        $sale = $this->actingAs($buyer)->withHeader('Idempotency-Key', 'buy-1')->postJson("/api/nft/listings/{$listing['id']}/buy", [
            'wallet_address' => '0xbuyer',
            'buyer_wallet' => '0xbuyer',
        ])->assertCreated()->assertJsonPath('data.status', 'COMPLETED')->json('data');

        $this->actingAs($buyer)->withHeader('Idempotency-Key', 'buy-1')->postJson("/api/nft/listings/{$listing['id']}/buy", [
            'wallet_address' => '0xbuyer',
            'buyer_wallet' => '0xbuyer',
        ])->assertCreated()->assertJsonPath('data.id', $sale['id']);

        $this->assertSame($buyer->id, Nft::query()->findOrFail($nft['id'])->user_id);
        $this->assertSame('sold', NftListing::query()->findOrFail($listing['id'])->status);
        $this->assertDatabaseCount('nft_sales', 1);
        $this->assertDatabaseHas('reservations', ['reservation_id' => $sale['reservation_id'], 'status' => Reservation::STATUS_CONSUMED, 'purpose' => 'nft_purchase']);
        $this->assertSame('80.000000000000000000', (string) Account::query()->where('user_id', $buyer->id)->where('asset', 'EXA')->where('account_type', 'funding')->firstOrFail()->balance);
        $this->assertSame('18.500000000000000000', (string) Account::query()->where('user_id', $seller->id)->where('asset', 'EXA')->where('account_type', 'nft_seller_payable')->firstOrFail()->balance);
        $this->assertSame('0.500000000000000000', (string) Account::query()->whereNull('user_id')->where('asset', 'EXA')->where('account_type', 'nft_marketplace_fee_revenue')->firstOrFail()->balance);
        $this->assertSame('1.000000000000000000', (string) Account::query()->where('asset', 'EXA')->where('account_type', 'nft_royalty_payable')->firstOrFail()->balance);
    }

    public function test_nft_reconciliation_detects_listing_owner_mismatch(): void
    {
        $seller = User::factory()->create();
        $other = User::factory()->create();
        $nft = app(NftService::class)->mint($seller, ['utility_type' => 'creator_access', 'name' => 'Mismatch NFT', 'wallet_address' => '0xseller', 'collection_name' => 'Mismatch']);
        $listing = app(NftService::class)->createListing($seller, $nft->id, ['wallet_address' => '0xseller', 'price_exa' => '5']);
        Nft::query()->whereKey($nft->id)->update(['user_id' => $other->id]);

        $result = app(NftService::class)->reconciliation();
        $this->assertSame('FAIL', $result['status']);
        $this->assertDatabaseHas('nft_reconciliation_breaks', ['break_type' => 'listing_owner_mismatch', 'status' => 'OPEN']);
        $this->assertSame($listing->id, NftListing::query()->findOrFail($listing->id)->id);
    }

    public function test_bids_use_canonical_reservations_and_release_outbid_hold(): void
    {
        $seller = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        Account::query()->create(['user_id' => $first->id, 'account_type' => 'funding', 'asset' => 'EXA', 'balance' => '50.000000000000000000']);
        Account::query()->create(['user_id' => $second->id, 'account_type' => 'funding', 'asset' => 'EXA', 'balance' => '60.000000000000000000']);
        $nft = app(NftService::class)->mint($seller, ['utility_type' => 'creator_access', 'name' => 'Auction NFT', 'wallet_address' => '0xseller', 'collection_name' => 'Auction']);
        $auction = app(NftService::class)->createAuction($seller, $nft->id, ['wallet_address' => '0xseller', 'starting_price_exa' => '10', 'ends_at' => now()->addDay()]);

        $firstBid = app(NftService::class)->placeBid($first, $auction->id, ['wallet_address' => '0xfirst', 'bid_amount_exa' => '12']);
        $secondBid = app(NftService::class)->placeBid($second, $auction->id, ['wallet_address' => '0xsecond', 'bid_amount_exa' => '15']);

        $this->assertDatabaseHas('reservations', ['reservation_id' => $firstBid->bid_reservation_id, 'status' => Reservation::STATUS_RELEASED]);
        $this->assertDatabaseHas('reservations', ['reservation_id' => $secondBid->bid_reservation_id, 'status' => Reservation::STATUS_ACTIVE, 'purpose' => 'nft_bid']);
    }

    public function test_stolen_or_ip_report_suspends_active_listing_for_moderation(): void
    {
        $seller = User::factory()->create();
        $reporter = User::factory()->create();
        $nft = app(NftService::class)->mint($seller, ['utility_type' => 'creator_access', 'name' => 'Reported NFT', 'wallet_address' => '0xseller', 'collection_name' => 'Reported']);
        $listing = app(NftService::class)->createListing($seller, $nft->id, ['wallet_address' => '0xseller', 'price_exa' => '8']);

        $this->actingAs($reporter)->postJson("/api/nft/assets/{$nft->id}/reports", [
            'report_type' => 'COPYRIGHT_IP',
            'listing_id' => $listing->id,
            'reason' => 'Looks like copied artwork.',
        ])->assertCreated()->assertJsonPath('data.status', 'OPEN');

        $this->assertSame('suspended', NftListing::query()->findOrFail($listing->id)->status);
        $this->assertSame('REPORTED', Nft::query()->findOrFail($nft->id)->moderation_status);
    }

    public function test_chain_reorg_moves_nft_to_manual_review_and_suspends_listing(): void
    {
        $seller = User::factory()->create();
        $nft = app(NftService::class)->mint($seller, ['utility_type' => 'creator_access', 'name' => 'Reorg NFT', 'wallet_address' => '0xseller', 'collection_name' => 'Reorg']);
        $listing = app(NftService::class)->createListing($seller, $nft->id, ['wallet_address' => '0xseller', 'price_exa' => '8']);

        app(NftService::class)->syncBlockchainEvent([
            'tx_hash' => '0xreorg',
            'event' => 'CHAIN_REORG',
            'operation' => 'MINT',
            'chain' => 'base',
            'nft_uuid' => $nft->nft_uuid,
            'status' => 'REORG_PENDING',
        ]);

        $this->assertSame('manual_review', Nft::query()->findOrFail($nft->id)->status);
        $this->assertSame('REORG_PENDING', Nft::query()->findOrFail($nft->id)->mint_status);
        $this->assertSame('suspended', NftListing::query()->findOrFail($listing->id)->status);
        $this->assertDatabaseHas('nft_reconciliation_breaks', ['nft_id' => $nft->id, 'break_type' => 'chain_reorg', 'status' => 'OPEN']);
    }
}
