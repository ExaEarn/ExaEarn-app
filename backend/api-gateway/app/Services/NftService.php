<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Nft;
use App\Models\NftAuction;
use App\Models\NftChainTransaction;
use App\Models\NftCollection;
use App\Models\NftListing;
use App\Models\NftReconciliationBreak;
use App\Models\NftRevenueEvent;
use App\Models\NftReport;
use App\Models\NftSale;
use App\Models\NftSubscription;
use App\Models\NftUpgrade;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use App\Services\FinancialDecimal;

class NftService
{
    private const SCALE = 8;

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BlockchainService $blockchain,
        private readonly ReservationService $reservations,
    ) {
    }

    public function dashboard(User $user): array
    {
        $owned = Nft::query()->where('user_id', $user->id)->get();
        $sales = NftSale::query()->where(function ($query) use ($user): void {
            $query->where('buyer_user_id', $user->id)->orWhere('seller_user_id', $user->id);
        })->get();
        $summary = [
            'total_assets_exa' => $owned->reduce(fn (string $sum, Nft $nft): string => $this->add($sum, (string) $nft->current_value_exa), '0'),
            'earnings_generated_exa' => $owned->reduce(fn (string $sum, Nft $nft): string => $this->add($sum, (string) $nft->earnings_generated_exa), '0'),
            'platform_fees_paid_exa' => $sales->reduce(fn (string $sum, NftSale $sale): string => $this->add($sum, (string) $sale->platform_fee_exa), '0'),
            'active_positions' => Nft::query()->where('user_id', $user->id)->where('status', 'active')->count(),
            'active_listings' => NftListing::query()->where('seller_user_id', $user->id)->where('status', 'active')->count(),
        ];
        return [
            'summary' => $summary,
            'owned_assets' => Nft::query()->where('user_id', $user->id)->count(),
            'active_listings' => NftListing::query()->where('seller_user_id', $user->id)->where('status', 'active')->count(),
            'purchases' => NftSale::query()->where('buyer_user_id', $user->id)->count(),
            'sales' => NftSale::query()->where('seller_user_id', $user->id)->count(),
            'chain_status' => config('blockchain.base_url') ? 'CONFIGURED' : 'LOCAL_PENDING_ONLY',
            'software_controls' => [
                'canonical_ledger' => true,
                'canonical_reservations' => true,
                'ownership_projection' => true,
                'external_chain_finality_required' => true,
                'marketplace_fees' => true,
                'royalties' => true,
            ],
            'upgrade_prompts' => [],
            'earn' => ['active_positions' => []],
            'fiat_bridge' => ['profiles' => []],
            'rwa_panel' => ['assets' => $owned->where('utility_type', 'agrishare')->values()->all()],
            'ai_insights' => ['premium_access' => $owned->where('utility_type', 'ai_portfolio')->isNotEmpty(), 'reports_available' => 0],
            'credit_panel' => ['credit_lines' => []],
        ];
    }

    public function collections(): Collection
    {
        return NftCollection::query()->withCount('nfts')->latest()->get();
    }

    public function marketplace(array $filters = []): Collection
    {
        return NftListing::query()
            ->with('nft.collection')
            ->where('status', 'active')
            ->when($filters['utility_type'] ?? null, fn ($query, string $type) => $query->whereHas('nft', fn ($nft) => $nft->where('utility_type', $type)))
            ->latest()
            ->get();
    }

    public function myNfts(User $user): Collection
    {
        return Nft::query()->with(['collection', 'listings' => fn ($query) => $query->latest()->limit(3)])->where('user_id', $user->id)->latest()->get();
    }

    public function createCollection(array $payload): NftCollection
    {
        $royalty = (int) ($payload['royalty_percentage'] ?? 750);
        if ($royalty < 0 || $royalty > (int) config('nft.max_royalty_bps', 2000)) {
            throw new RuntimeException('Royalty exceeds configured NFT policy.');
        }

        return NftCollection::query()->firstOrCreate(
            ['slug' => Str::slug($payload['name'])],
            [
                'name' => $payload['name'],
                'creator_wallet' => $payload['creator_wallet'] ?? null,
                'royalty_percentage' => $royalty,
                'utility_type' => $payload['utility_type'],
                'chain' => $payload['chain'] ?? config('nft.default_chain', 'base'),
                'verification_status' => 'UNDER_REVIEW',
                'status' => 'DRAFT',
                'metadata' => $payload['metadata'] ?? [],
            ]
        );
    }

    public function mint(User $user, array $payload): Nft
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = Nft::query()->where('user_id', $user->id)->where('metadata->idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing;
        }
        $metadata = [
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'image' => $payload['image'] ?? null,
            'attributes' => $payload['metadata']['attributes'] ?? [],
        ];
        $collection = $this->collectionForMint($payload);
        $nft = Nft::query()->create([
            'nft_uuid' => (string) Str::uuid(),
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'utility_type' => $payload['utility_type'],
            'name' => $payload['name'],
            'symbol' => strtoupper((string) ($payload['symbol'] ?? 'EXANFT')),
            'creator_wallet' => $payload['creator_wallet'] ?? $payload['wallet_address'],
            'owner_wallet' => $payload['wallet_address'],
            'tier' => $payload['tier'] ?? 'standard',
            'status' => 'pending_mint',
            'mint_status' => 'PENDING',
            'moderation_status' => 'PENDING',
            'chain' => $payload['chain'] ?? config('nft.default_chain', 'base'),
            'token_standard' => $payload['token_standard'] ?? 'ERC-721',
            'metadata_url' => 'exaearn://nft/metadata/'.hash('sha256', json_encode($metadata)),
            'metadata_hash' => hash('sha256', json_encode($metadata)),
            'media_url' => $payload['image'] ?? null,
            'current_value_exa' => $this->fmt((string) ($payload['current_value_exa'] ?? '0')),
            'metadata' => array_merge($payload['metadata'] ?? [], ['idempotency_key' => $idempotencyKey, 'finality_required' => true]),
        ]);

        try {
            $result = $this->blockchain->mintFinancialNft(['nft_uuid' => $nft->nft_uuid, 'owner_wallet' => $nft->owner_wallet, 'metadata_uri' => $nft->metadata_url, 'chain' => $nft->chain]);
            NftChainTransaction::query()->create(['nft_id' => $nft->id, 'operation' => 'MINT', 'chain' => $nft->chain ?? 'base', 'tx_hash' => $result['tx_hash'] ?? null, 'status' => 'SUBMITTED', 'payload' => $result]);
            $nft->update(['mint_status' => 'SUBMITTED', 'mint_tx_hash' => $result['tx_hash'] ?? null]);
        } catch (RuntimeException) {
            NftChainTransaction::query()->create(['nft_id' => $nft->id, 'operation' => 'MINT', 'chain' => $nft->chain ?? 'base', 'status' => 'PENDING_PROVIDER_CONFIGURATION', 'payload' => ['reason' => 'blockchain_service_not_configured']]);
        }

        return $nft->fresh('collection');
    }

    public function createListing(User $user, int $nftId, array $payload): NftListing
    {
        $nft = Nft::query()->whereKey($nftId)->lockForUpdate()->firstOrFail();
        $this->assertOwner($user, $nft, $payload['wallet_address']);
        if (NftListing::query()->where('nft_id', $nft->id)->where('status', 'active')->exists()) {
            throw new RuntimeException('NFT already has an active listing.');
        }
        $price = $this->fmt((string) $payload['price_exa']);
        return NftListing::query()->create([
            'listing_uuid' => (string) Str::uuid(),
            'nft_id' => $nft->id,
            'seller_user_id' => $user->id,
            'seller_wallet' => $payload['wallet_address'],
            'price_exa' => $price,
            'settlement_asset' => strtoupper((string) ($payload['settlement_asset'] ?? 'EXA')),
            'listing_type' => $payload['listing_type'] ?? 'fixed_price',
            'status' => 'active',
            'expires_at' => $payload['expires_at'] ?? null,
            'pricing_snapshot' => ['engine' => 'PricingPolicyEngine-ready', 'seller_fee_bps' => config('nft.marketplace_fee_bps', 250)],
            'metadata' => ['ownership_verified_at' => now()->toISOString()],
        ]);
    }

    public function buyListing(User $buyer, int $listingId, array $payload): NftSale
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = NftSale::query()->where('buyer_user_id', $buyer->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing;
        }
        if (!$idempotencyKey) throw new RuntimeException('Idempotency-Key is required for NFT purchase.');

        return DB::transaction(function () use ($buyer, $listingId, $payload, $idempotencyKey): NftSale {
            $listing = NftListing::query()->with('nft.collection')->whereKey($listingId)->lockForUpdate()->firstOrFail();
            if ($listing->status !== 'active') throw new RuntimeException('Listing is not active.');
            $nft = Nft::query()->whereKey($listing->nft_id)->lockForUpdate()->firstOrFail();
            if ((int) $nft->user_id === (int) $buyer->id) throw new RuntimeException('Buyer already owns this NFT.');
            if ((int) $listing->seller_user_id !== (int) $nft->user_id) throw new RuntimeException('Listing seller is no longer NFT owner.');

            $asset = strtoupper((string) ($listing->settlement_asset ?? 'EXA'));
            $price = $this->fmt((string) $listing->price_exa);
            $platformFee = $this->mul($price, $this->bps(config('nft.marketplace_fee_bps', 250)));
            $royalty = $this->mul($price, $this->bps($nft->collection?->royalty_percentage ?? 0));
            $networkCost = $this->fmt((string) config('nft.network_cost_exa', '0'));
            $sellerProceeds = $this->sub($this->sub($price, $platformFee), $royalty);
            $reference = 'NFT-SALE-'.hash('sha256', $buyer->id.':'.$listing->id.':'.$idempotencyKey);

            $buyerFunding = $this->ledger->getOrCreateAccount($buyer->id, 'funding', $asset);
            $reservation = $this->reservations->reserve($buyerFunding->id, $asset, $price, 'nft_purchase', 'nft_listing', (string) $listing->id, 'NFT-PURCHASE-'.$idempotencyKey, ['nft_id' => $nft->id, 'reference' => $reference]);
            $sellerPayable = $this->ledger->getOrCreateAccount($listing->seller_user_id, 'nft_seller_payable', $asset);
            $feeRevenue = $this->ledger->getOrCreateAccount(null, 'nft_marketplace_fee_revenue', $asset);
            $royaltyPayable = $this->ledger->getOrCreateAccount($nft->collection?->metadata['creator_user_id'] ?? $listing->seller_user_id, 'nft_royalty_payable', $asset);
            $networkExpense = $this->ledger->getOrCreateAccount(null, 'nft_network_fee_expense', $asset);

            $this->ledger->postDoubleEntry($reference, 'NFT marketplace purchase', [
                ['account_id' => $buyerFunding->id, 'amount' => $this->sub('0', $price), 'asset' => $asset, 'user_id' => $buyer->id],
                ['account_id' => $sellerPayable->id, 'amount' => $sellerProceeds, 'asset' => $asset, 'user_id' => $listing->seller_user_id],
                ['account_id' => $feeRevenue->id, 'amount' => $platformFee, 'asset' => $asset],
                ['account_id' => $royaltyPayable->id, 'amount' => $royalty, 'asset' => $asset],
                ['account_id' => $networkExpense->id, 'amount' => $networkCost, 'asset' => $asset],
                ['account_id' => $feeRevenue->id, 'amount' => $this->sub('0', $networkCost), 'asset' => $asset],
            ], 'nft_purchase', ['source' => 'nft_marketplace', 'listing_id' => $listing->id, 'nft_id' => $nft->id]);
            $this->reservations->consume($reservation->reservation_id, $price, ['ledger_reference' => $reference]);

            $sale = NftSale::query()->create([
                'nft_id' => $nft->id,
                'listing_id' => $listing->id,
                'buyer_user_id' => $buyer->id,
                'seller_user_id' => $listing->seller_user_id,
                'buyer_wallet' => $payload['buyer_wallet'] ?? $payload['wallet_address'],
                'seller_wallet' => $listing->seller_wallet,
                'sale_price_exa' => $price,
                'platform_fee_exa' => $platformFee,
                'royalty_fee_exa' => $royalty,
                'network_cost_exa' => $networkCost,
                'settlement_asset' => $asset,
                'status' => 'COMPLETED',
                'tx_hash' => $reference,
                'idempotency_key' => $idempotencyKey,
                'reservation_id' => $reservation->reservation_id,
                'metadata' => ['ledger_reference' => $reference, 'seller_proceeds' => $sellerProceeds],
            ]);
            $listing->update(['status' => 'sold']);
            $nft->update(['user_id' => $buyer->id, 'owner_wallet' => $payload['buyer_wallet'] ?? $payload['wallet_address'], 'last_event_tx_hash' => $reference, 'last_synced_at' => now()]);
            NftRevenueEvent::query()->create(['nft_id' => $nft->id, 'user_id' => $buyer->id, 'event_type' => 'marketplace_sale', 'gross_amount_exa' => $price, 'platform_revenue_exa' => $platformFee, 'tx_hash' => $reference, 'metadata' => ['royalty' => $royalty]]);
            ActivityLog::query()->create(['user_id' => $buyer->id, 'type' => 'nft', 'action' => 'purchase.completed', 'status' => 'success', 'data' => ['nft_id' => $nft->id, 'listing_id' => $listing->id, 'reference' => $reference]]);

            return $sale;
        });
    }

    public function upgrade(User $user, int $nftId, array $payload): Nft
    {
        $nft = Nft::query()->whereKey($nftId)->firstOrFail();
        $this->assertOwner($user, $nft, $payload['wallet_address']);
        NftUpgrade::query()->create(['nft_id' => $nft->id, 'user_id' => $user->id, 'from_tier' => $nft->tier, 'to_tier' => $payload['target_tier'] ?? $nft->tier, 'from_level' => $nft->level, 'to_level' => $payload['target_level'] ?? ($nft->level + 1), 'metadata' => ['reason' => $payload['reason'] ?? null]]);
        $nft->update(['tier' => $payload['target_tier'] ?? $nft->tier, 'level' => $payload['target_level'] ?? ($nft->level + 1)]);
        return $nft->fresh();
    }

    public function subscribe(User $user, int $nftId, array $payload): NftSubscription
    {
        $nft = Nft::query()->whereKey($nftId)->firstOrFail();
        $this->assertOwner($user, $nft, $payload['wallet_address']);
        return NftSubscription::query()->create(['nft_id' => $nft->id, 'user_id' => $user->id, 'plan' => $payload['plan'], 'status' => 'active', 'fee_exa' => '0', 'starts_at' => now(), 'ends_at' => now()->addDays((int) ($payload['duration_days'] ?? 30)), 'metadata' => ['policy' => 'owner_only']]);
    }

    public function createAuction(User $user, int $nftId, array $payload): NftAuction
    {
        $nft = Nft::query()->whereKey($nftId)->firstOrFail();
        $this->assertOwner($user, $nft, $payload['wallet_address']);
        return NftAuction::query()->create(['auction_uuid' => (string) Str::uuid(), 'nft_id' => $nft->id, 'seller_user_id' => $user->id, 'seller_wallet' => $payload['wallet_address'], 'starting_price_exa' => $this->fmt((string) $payload['starting_price_exa']), 'status' => 'active', 'ends_at' => $payload['ends_at'], 'metadata' => ['reserve_price_exa' => $payload['reserve_price_exa'] ?? null]]);
    }

    public function placeBid(User $user, int $auctionId, array $payload): NftAuction
    {
        $auction = NftAuction::query()->whereKey($auctionId)->lockForUpdate()->firstOrFail();
        $bid = $this->fmt((string) $payload['bid_amount_exa']);
        if ($this->compare($bid, (string) $auction->current_highest_bid_exa) <= 0 || $this->compare($bid, (string) $auction->starting_price_exa) < 0) throw new RuntimeException('Bid is below the required amount.');
        $asset = 'EXA';
        $funding = $this->ledger->getOrCreateAccount($user->id, 'funding', $asset);
        $reservation = $this->reservations->reserve($funding->id, $asset, $bid, 'nft_bid', 'nft_auction', (string) $auction->id, 'NFT-BID-'.$auction->id.'-'.$user->id, ['auction_id' => $auction->id]);
        if ($auction->bid_reservation_id) {
            $this->reservations->release($auction->bid_reservation_id, null, ['reason' => 'outbid']);
        }
        $auction->update(['current_highest_bid_exa' => $bid, 'highest_bidder_user_id' => $user->id, 'highest_bidder_wallet' => $payload['wallet_address'], 'bid_reservation_id' => $reservation->reservation_id]);
        return $auction->fresh();
    }

    public function finalizeAuction(int $auctionId): NftAuction
    {
        $auction = NftAuction::query()->whereKey($auctionId)->firstOrFail();
        $auction->update(['status' => $auction->highest_bidder_user_id ? 'completed' : 'expired']);
        return $auction->fresh();
    }

    public function syncBlockchainEvent(array $payload): array
    {
        $tx = NftChainTransaction::query()->updateOrCreate(['tx_hash' => $payload['tx_hash'] ?? null], ['operation' => $payload['operation'] ?? 'UNKNOWN', 'chain' => $payload['chain'] ?? 'base', 'status' => $payload['status'] ?? 'CONFIRMED', 'confirmations' => (int) ($payload['confirmations'] ?? 0), 'receipt' => $payload]);
        $event = strtoupper((string) ($payload['event'] ?? ''));
        if ($event === 'NFTMINTED' && isset($payload['nft_uuid'])) {
            Nft::query()->where('nft_uuid', $payload['nft_uuid'])->update(['mint_status' => 'CONFIRMED', 'status' => 'active', 'token_id' => $payload['token_id'] ?? null, 'contract_address' => $payload['contract_address'] ?? null, 'minted_at' => now(), 'last_synced_at' => now()]);
        }
        if (in_array($event, ['REORG', 'CHAIN_REORG', 'NFT_REORG'], true) && isset($payload['nft_uuid'])) {
            $nft = Nft::query()->where('nft_uuid', $payload['nft_uuid'])->first();
            if ($nft) {
                $nft->update(['mint_status' => 'REORG_PENDING', 'status' => 'manual_review', 'last_synced_at' => now()]);
                NftListing::query()->where('nft_id', $nft->id)->where('status', 'active')->update(['status' => 'suspended']);
                NftReconciliationBreak::query()->firstOrCreate(
                    ['nft_id' => $nft->id, 'break_type' => 'chain_reorg', 'status' => 'OPEN'],
                    ['severity' => 'critical', 'evidence' => $payload]
                );
            }
        }
        return ['synced' => true, 'transaction_id' => $tx->id];
    }

    public function reconciliation(): array
    {
        $findings = [];
        $activeBad = NftListing::query()->where('status', 'active')->whereHas('nft', fn ($query) => $query->whereColumn('nfts.user_id', '!=', 'nft_listings.seller_user_id'))->count();
        if ($activeBad > 0) $findings[] = ['type' => 'listing_owner_mismatch', 'count' => $activeBad];
        $pendingMints = Nft::query()->whereIn('mint_status', ['PENDING', 'SUBMITTED', 'CONFIRMING'])->count();
        if ($pendingMints > 0) $findings[] = ['type' => 'pending_chain_finality', 'count' => $pendingMints, 'severity' => 'info'];
        foreach ($findings as $finding) {
            if (($finding['severity'] ?? 'medium') !== 'info') {
                NftReconciliationBreak::query()->firstOrCreate(['break_type' => $finding['type'], 'status' => 'OPEN'], ['severity' => 'high', 'evidence' => $finding]);
            }
        }
        return ['status' => collect($findings)->contains(fn ($f) => ($f['severity'] ?? 'medium') !== 'info') ? 'FAIL' : 'PASS', 'findings' => $findings];
    }

    public function report(User $user, int $nftId, array $payload): NftReport
    {
        $nft = Nft::query()->findOrFail($nftId);
        $type = strtoupper((string) $payload['report_type']);
        if (!in_array($type, ['STOLEN_ASSET', 'COPYRIGHT_IP', 'MALICIOUS_METADATA', 'FRAUD', 'OTHER'], true)) {
            throw new RuntimeException('Unsupported NFT report type.');
        }
        $report = NftReport::query()->create([
            'nft_id' => $nft->id,
            'listing_id' => $payload['listing_id'] ?? null,
            'reported_by_user_id' => $user->id,
            'report_type' => $type,
            'status' => 'OPEN',
            'reason' => $payload['reason'] ?? null,
            'evidence' => $payload['evidence'] ?? [],
        ]);
        if (in_array($type, ['STOLEN_ASSET', 'COPYRIGHT_IP'], true)) {
            $nft->update(['moderation_status' => 'REPORTED']);
            NftListing::query()->where('nft_id', $nft->id)->where('status', 'active')->update(['status' => 'suspended']);
        }
        return $report;
    }

    private function collectionForMint(array $payload): NftCollection
    {
        $name = $payload['collection_name'] ?? 'ExaEarn Creator Collection';
        return $this->createCollection(['name' => $name, 'utility_type' => $payload['utility_type'], 'creator_wallet' => $payload['creator_wallet'] ?? $payload['wallet_address'], 'royalty_percentage' => $payload['royalty_percentage'] ?? 750, 'metadata' => ['creator_user_id' => null]]);
    }

    private function assertOwner(User $user, Nft $nft, string $wallet): void
    {
        if ((int) $nft->user_id !== (int) $user->id || !hash_equals((string) $nft->owner_wallet, $wallet)) {
            throw new RuntimeException('NFT ownership could not be verified.');
        }
    }

    private function bps(int|string $bps): string { return FinancialDecimal::div((string) $bps, '10000', self::SCALE); }
    private function fmt(string $value): string { return FinancialDecimal::normalize($value, self::SCALE); }
    private function add(string $left, string $right): string { return FinancialDecimal::add($left, $right, self::SCALE); }
    private function sub(string $left, string $right): string { return FinancialDecimal::sub($left, $right, self::SCALE); }
    private function mul(string $left, string $right): string { return FinancialDecimal::mul($left, $right, self::SCALE); }
    private function compare(string $left, string $right): int { return FinancialDecimal::compare($left, $right, self::SCALE); }
}
