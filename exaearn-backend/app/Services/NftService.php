<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Nft;
use App\Models\NftAuction;
use App\Models\NftCollection;
use App\Models\NftCreditLine;
use App\Models\NftFiatProfile;
use App\Models\NftListing;
use App\Models\NftRevenueEvent;
use App\Models\NftSale;
use App\Models\NftStakingPosition;
use App\Models\NftSubscription;
use App\Models\NftUpgrade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class NftService
{
    private const SCALE = 8;

    public function __construct(private readonly BlockchainService $blockchain)
    {
    }

    public function dashboard(User $user): array
    {
        $ownedNfts = Nft::query()->where('user_id', $user->id)->latest()->get();
        $revenueEvents = NftRevenueEvent::query()->where('user_id', $user->id)->latest()->get();
        $listings = NftListing::query()->where('seller_user_id', $user->id)->where('status', 'active')->count();
        $staking = NftStakingPosition::query()->where('user_id', $user->id)->where('status', 'active')->get();
        $fiatProfiles = NftFiatProfile::query()->where('user_id', $user->id)->where('status', 'active')->get();
        $creditLines = NftCreditLine::query()->where('user_id', $user->id)->where('status', 'active')->get();
        $subscriptions = NftSubscription::query()->where('user_id', $user->id)->where('status', 'active')->get();

        return [
            'summary' => [
                'total_assets_exa' => $ownedNfts->sum(fn (Nft $nft) => (float) $nft->current_value_exa),
                'earnings_generated_exa' => $ownedNfts->sum(fn (Nft $nft) => (float) $nft->earnings_generated_exa),
                'platform_fees_paid_exa' => $revenueEvents->sum(fn (NftRevenueEvent $event) => (float) $event->gross_amount_exa),
                'active_positions' => $staking->count(),
                'active_listings' => $listings,
            ],
            'upgrade_prompts' => $this->buildUpgradePrompts($ownedNfts, $subscriptions),
            'financial_dashboard' => $ownedNfts->take(6)->map(fn (Nft $nft) => $this->transformNft($nft))->values(),
            'earn' => [
                'active_positions' => $staking->map(fn (NftStakingPosition $position) => [
                    'nft_id' => $position->nft_id,
                    'staked_amount_exa' => $position->staked_amount_exa,
                    'accumulated_rewards_exa' => $position->accumulated_rewards_exa,
                    'platform_commission_bps' => $position->platform_commission_bps,
                    'status' => $position->status,
                ])->values(),
            ],
            'fiat_bridge' => [
                'profiles' => $fiatProfiles->map(fn (NftFiatProfile $profile) => [
                    'nft_id' => $profile->nft_id,
                    'daily_limit_usd' => $profile->daily_limit_usd,
                    'withdrawal_fee_bps' => $profile->withdrawal_fee_bps,
                    'spread_bps' => $profile->spread_bps,
                    'speed_tier' => $profile->speed_tier,
                ])->values(),
            ],
            'rwa_panel' => [
                'assets' => Nft::query()->where('utility_type', 'agrishare')->latest()->take(12)->get()->map(fn (Nft $nft) => $this->transformNft($nft))->values(),
            ],
            'ai_insights' => [
                'premium_access' => $subscriptions->contains(fn (NftSubscription $subscription) => in_array($subscription->plan, ['pro', 'institutional'], true)),
                'reports_available' => $ownedNfts->whereIn('utility_type', ['yield_passport', 'ai_portfolio', 'access'])->count(),
            ],
            'credit_panel' => [
                'credit_lines' => $creditLines->map(fn (NftCreditLine $line) => [
                    'nft_id' => $line->nft_id,
                    'credit_limit_exa' => $line->credit_limit_exa,
                    'available_credit_exa' => $line->available_credit_exa,
                    'interest_bps' => $line->interest_bps,
                    'credit_score' => $line->credit_score,
                ])->values(),
            ],
            'marketplace' => [
                'featured' => $this->marketplace()->take(8)->values(),
            ],
        ];
    }

    public function collections(): Collection
    {
        return NftCollection::query()->latest()->get()->map(fn (NftCollection $collection) => [
            'id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'utility_type' => $collection->utility_type,
            'royalty_percentage' => $collection->royalty_percentage,
            'creator_wallet' => $collection->creator_wallet,
        ]);
    }

    public function marketplace(array $filters = []): Collection
    {
        $query = Nft::query()
            ->with(['collection', 'listings'])
            ->where(function ($query): void {
                $query->whereHas('listings', fn ($listingQuery) => $listingQuery->where('status', 'active'))
                    ->orWhereIn('utility_type', ['staking', 'boost', 'fee', 'fiat_bridge', 'agrishare']);
            });

        if (!empty($filters['utility_type']) && $filters['utility_type'] !== 'all') {
            $query->where('utility_type', (string) $filters['utility_type']);
        }

        if (!empty($filters['phase']) && $filters['phase'] !== 'all') {
            $phase = (string) $filters['phase'];
            $allowed = collect(config('nft.utilities', []))
                ->filter(fn (array $utility) => ($utility['phase'] ?? null) === $phase)
                ->keys()
                ->values();
            if ($allowed->isNotEmpty()) {
                $query->whereIn('utility_type', $allowed->all());
            }
        }

        return $query->latest()->get()->map(fn (Nft $nft) => $this->transformNft($nft));
    }

    public function myNfts(User $user): Collection
    {
        return Nft::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (Nft $nft) => $this->transformNft($nft));
    }

    public function createCollection(array $payload): NftCollection
    {
        return NftCollection::query()->firstOrCreate(
            ['slug' => Str::slug((string) $payload['name'])],
            [
                'name' => (string) $payload['name'],
                'creator_wallet' => $payload['creator_wallet'] ?? null,
                'royalty_percentage' => (int) ($payload['royalty_percentage'] ?? 750),
                'utility_type' => (string) $payload['utility_type'],
                'metadata' => $payload['metadata'] ?? [],
            ],
        );
    }

    public function mint(User $user, array $payload): Nft
    {
        $utility = (string) $payload['utility_type'];
        $config = $this->utilityConfig($utility);
        $collection = $this->createCollection([
            'name' => $payload['collection_name'] ?? Str::headline(str_replace('_', ' ', $utility)) . ' Collection',
            'creator_wallet' => $payload['creator_wallet'] ?? $payload['wallet_address'],
            'royalty_percentage' => $payload['royalty_percentage'] ?? 750,
            'utility_type' => $utility,
            'metadata' => ['phase' => $config['phase'] ?? null],
        ]);

        $mintFee = (string) ($config['mint_fee_exa'] ?? '0');
        $metadata = [
            'description' => $payload['description'] ?? null,
            'image' => $payload['image'] ?? null,
            'wallet_address' => $payload['wallet_address'],
            'financial_profile' => $payload['financial_profile'] ?? [],
            'phase' => $config['phase'] ?? null,
        ];

        $onChain = [
            'token_id' => null,
            'contract_address' => null,
            'tx_hash' => null,
            'metadata_url' => $this->makeMetadataUrl($metadata),
        ];

        if (config('nft.contract_enabled', true)) {
            $onChain = $this->blockchain->mintFinancialNft([
                'wallet_address' => $payload['wallet_address'],
                'utility_type' => $utility,
                'tier' => $payload['tier'] ?? 'standard',
                'name' => $payload['name'],
                'metadata' => $metadata,
                'mint_fee_exa' => $mintFee,
            ]);
        }

        $nft = DB::transaction(function () use ($collection, $user, $payload, $utility, $mintFee, $metadata, $onChain, $config): Nft {
            $nft = Nft::query()->create([
                'nft_uuid' => (string) Str::uuid(),
                'token_id' => $onChain['token_id'] ?? null,
                'contract_address' => $onChain['contract_address'] ?? null,
                'collection_id' => $collection->id,
                'user_id' => $user->id,
                'utility_type' => $utility,
                'name' => (string) $payload['name'],
                'symbol' => $payload['symbol'] ?? 'EXANFT',
                'creator_wallet' => $payload['creator_wallet'] ?? $payload['wallet_address'],
                'owner_wallet' => $payload['wallet_address'],
                'tier' => $payload['tier'] ?? 'standard',
                'level' => 1,
                'status' => 'active',
                'mint_fee_exa' => $mintFee,
                'current_value_exa' => $payload['current_value_exa'] ?? $mintFee,
                'earnings_generated_exa' => '0',
                'metadata_url' => $onChain['metadata_url'] ?? $this->makeMetadataUrl($metadata),
                'mint_tx_hash' => $onChain['tx_hash'] ?? null,
                'last_event_tx_hash' => $onChain['tx_hash'] ?? null,
                'last_synced_at' => now(),
                'benefits' => $config['base_benefits'] ?? [],
                'upgrade_options' => $this->defaultUpgradeOptions($utility),
                'metadata' => $metadata,
            ]);

            $this->bootstrapUtilityRecords($nft, $user, $payload);
            $this->recordRevenue($nft, $user->id, 'mint_fee', $mintFee, $mintFee, '0', $onChain['tx_hash'] ?? null, ['utility_type' => $utility]);
            $this->logAudit($user->id, 'nft_minted', ['nft_id' => $nft->id, 'utility_type' => $utility]);

            return $nft;
        });

        return $nft->fresh();
    }

    public function upgrade(User $user, int $nftId, array $payload): Nft
    {
        $nft = Nft::query()->findOrFail($nftId);
        $this->guardOwnership($nft, $user, (string) $payload['wallet_address']);

        $config = $this->utilityConfig($nft->utility_type);
        $upgradeFee = (string) ($config['upgrade_fee_exa'] ?? '0');
        $targetLevel = max($nft->level + 1, (int) ($payload['target_level'] ?? ($nft->level + 1)));
        $targetTier = (string) ($payload['target_tier'] ?? $nft->tier);

        $onChain = ['tx_hash' => null];
        if (config('nft.contract_enabled', true)) {
            $onChain = $this->blockchain->upgradeFinancialNft([
                'token_id' => (int) $nft->token_id,
                'wallet_address' => (string) $payload['wallet_address'],
                'target_tier' => $targetTier,
                'target_level' => $targetLevel,
                'upgrade_fee_exa' => $upgradeFee,
            ]);
        }

        DB::transaction(function () use ($nft, $user, $targetLevel, $targetTier, $upgradeFee, $onChain): void {
            NftUpgrade::query()->create([
                'nft_id' => $nft->id,
                'user_id' => $user->id,
                'from_tier' => $nft->tier,
                'to_tier' => $targetTier,
                'from_level' => $nft->level,
                'to_level' => $targetLevel,
                'upgrade_fee_exa' => $upgradeFee,
                'burn_amount_exa' => $upgradeFee,
                'tx_hash' => $onChain['tx_hash'] ?? null,
                'metadata' => ['utility_type' => $nft->utility_type],
            ]);

            $nft->tier = $targetTier;
            $nft->level = $targetLevel;
            $nft->current_value_exa = $this->add((string) $nft->current_value_exa, $upgradeFee);
            $nft->last_event_tx_hash = $onChain['tx_hash'] ?? $nft->last_event_tx_hash;
            $nft->last_synced_at = now();
            $nft->save();

            $this->recordRevenue($nft, $user->id, 'upgrade_fee', $upgradeFee, $upgradeFee, $upgradeFee, $onChain['tx_hash'] ?? null, ['target_level' => $targetLevel]);
            $this->logAudit($user->id, 'nft_upgraded', ['nft_id' => $nft->id, 'target_level' => $targetLevel]);
        });

        return $nft->fresh();
    }

    public function subscribe(User $user, int $nftId, array $payload): NftSubscription
    {
        $nft = Nft::query()->findOrFail($nftId);
        $this->guardOwnership($nft, $user, (string) $payload['wallet_address']);

        $config = $this->utilityConfig($nft->utility_type);
        $fee = (string) ($config['subscription_fee_exa'] ?? '0');
        $plan = (string) ($payload['plan'] ?? 'pro');

        $subscription = NftSubscription::query()->create([
            'nft_id' => $nft->id,
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => 'active',
            'fee_exa' => $fee,
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) ($payload['duration_days'] ?? 30)),
            'tx_hash' => null,
            'metadata' => ['wallet_address' => $payload['wallet_address']],
        ]);

        $this->recordRevenue($nft, $user->id, 'subscription_fee', $fee, $fee, '0', null, ['plan' => $plan]);
        $this->logAudit($user->id, 'nft_subscription_started', ['nft_id' => $nft->id, 'plan' => $plan]);

        return $subscription;
    }

    public function createListing(User $user, int $nftId, array $payload): NftListing
    {
        $nft = Nft::query()->findOrFail($nftId);
        $wallet = (string) $payload['wallet_address'];
        $this->guardOwnership($nft, $user, $wallet);
        $this->verifyBlockchainOwnership($nft, $wallet);

        $onChain = ['tx_hash' => null];
        if (config('nft.contract_enabled', true)) {
            $onChain = $this->blockchain->createFinancialNftListing([
                'token_id' => (int) $nft->token_id,
                'wallet_address' => $wallet,
                'price_exa' => (string) $payload['price_exa'],
                'listing_type' => (string) ($payload['listing_type'] ?? 'fixed_price'),
            ]);
        }

        $listing = NftListing::query()->create([
            'listing_uuid' => (string) Str::uuid(),
            'nft_id' => $nft->id,
            'seller_user_id' => $user->id,
            'seller_wallet' => $wallet,
            'price_exa' => (string) $payload['price_exa'],
            'listing_type' => (string) ($payload['listing_type'] ?? 'fixed_price'),
            'status' => 'active',
            'listing_tx_hash' => $onChain['tx_hash'] ?? null,
            'expires_at' => isset($payload['expires_at']) ? now()->parse((string) $payload['expires_at']) : now()->addDays(7),
            'metadata' => ['utility_type' => $nft->utility_type],
        ]);

        $this->logAudit($user->id, 'nft_listing_created', ['nft_id' => $nft->id, 'listing_id' => $listing->id]);
        return $listing;
    }

    public function buyListing(User $user, int $listingId, array $payload): NftSale
    {
        $listing = NftListing::query()->with('nft.collection')->findOrFail($listingId);
        if ($listing->status !== 'active') {
            throw new RuntimeException('Listing is not active.');
        }

        $nft = $listing->nft;
        $platformFee = $this->percentageOf((string) $listing->price_exa, (int) config('nft.marketplace_fee_bps', 250));
        $royaltyFee = $this->percentageOf((string) $listing->price_exa, (int) ($nft->collection->royalty_percentage ?? 0));

        $onChain = ['tx_hash' => null];
        if (config('nft.contract_enabled', true)) {
            $onChain = $this->blockchain->buyFinancialNftListing([
                'token_id' => (int) $nft->token_id,
                'buyer_wallet' => (string) $payload['wallet_address'],
                'price_exa' => (string) $listing->price_exa,
            ]);
        }

        $sale = DB::transaction(function () use ($user, $listing, $nft, $platformFee, $royaltyFee, $payload, $onChain): NftSale {
            $sale = NftSale::query()->create([
                'nft_id' => $nft->id,
                'listing_id' => $listing->id,
                'buyer_user_id' => $user->id,
                'seller_user_id' => $listing->seller_user_id,
                'buyer_wallet' => (string) $payload['wallet_address'],
                'seller_wallet' => $listing->seller_wallet,
                'sale_price_exa' => $listing->price_exa,
                'platform_fee_exa' => $platformFee,
                'royalty_fee_exa' => $royaltyFee,
                'tx_hash' => $onChain['tx_hash'] ?? null,
                'metadata' => ['utility_type' => $nft->utility_type],
            ]);

            $listing->status = 'sold';
            $listing->save();

            $nft->user_id = $user->id;
            $nft->owner_wallet = (string) $payload['wallet_address'];
            $nft->earnings_generated_exa = $this->add((string) $nft->earnings_generated_exa, $platformFee);
            $nft->last_event_tx_hash = $onChain['tx_hash'] ?? $nft->last_event_tx_hash;
            $nft->last_synced_at = now();
            $nft->save();

            $this->recordRevenue($nft, $user->id, 'marketplace_sale', (string) $listing->price_exa, $platformFee, '0', $onChain['tx_hash'] ?? null, ['listing_id' => $listing->id]);
            $this->logAudit($user->id, 'nft_listing_purchased', ['nft_id' => $nft->id, 'listing_id' => $listing->id]);

            return $sale;
        });

        return $sale;
    }

    public function createAuction(User $user, int $nftId, array $payload): NftAuction
    {
        $nft = Nft::query()->findOrFail($nftId);
        $wallet = (string) $payload['wallet_address'];
        $this->guardOwnership($nft, $user, $wallet);
        $this->verifyBlockchainOwnership($nft, $wallet);

        $onChain = ['tx_hash' => null];
        if (config('nft.contract_enabled', true)) {
            $onChain = $this->blockchain->createFinancialNftAuction([
                'token_id' => (int) $nft->token_id,
                'wallet_address' => $wallet,
                'starting_price_exa' => (string) $payload['starting_price_exa'],
                'ends_at' => now()->parse((string) $payload['ends_at'])->timestamp,
            ]);
        }

        return NftAuction::query()->create([
            'auction_uuid' => (string) Str::uuid(),
            'nft_id' => $nft->id,
            'seller_user_id' => $user->id,
            'seller_wallet' => $wallet,
            'starting_price_exa' => (string) $payload['starting_price_exa'],
            'current_highest_bid_exa' => '0',
            'status' => 'active',
            'auction_tx_hash' => $onChain['tx_hash'] ?? null,
            'starts_at' => now(),
            'ends_at' => now()->parse((string) $payload['ends_at']),
            'metadata' => ['utility_type' => $nft->utility_type],
        ]);
    }

    public function placeBid(User $user, int $auctionId, array $payload): NftAuction
    {
        $auction = NftAuction::query()->findOrFail($auctionId);
        if ($auction->status !== 'active') {
            throw new RuntimeException('Auction is not active.');
        }

        $bidAmount = (string) $payload['bid_amount_exa'];
        if ($this->compare($bidAmount, (string) $auction->current_highest_bid_exa) <= 0) {
            throw new RuntimeException('Bid must exceed the current highest bid.');
        }

        if (config('nft.contract_enabled', true)) {
            $this->blockchain->placeFinancialNftBid([
                'token_id' => (int) $auction->nft->token_id,
                'bidder_wallet' => (string) $payload['wallet_address'],
                'bid_amount_exa' => $bidAmount,
            ]);
        }

        $auction->current_highest_bid_exa = $bidAmount;
        $auction->highest_bidder_user_id = $user->id;
        $auction->highest_bidder_wallet = (string) $payload['wallet_address'];
        $auction->save();

        return $auction->fresh();
    }

    public function finalizeAuction(int $auctionId): NftAuction
    {
        $auction = NftAuction::query()->with('nft.collection')->findOrFail($auctionId);
        if (!$auction->highest_bidder_wallet) {
            throw new RuntimeException('Auction has no bids to finalize.');
        }

        $onChain = ['tx_hash' => null];
        if (config('nft.contract_enabled', true)) {
            $onChain = $this->blockchain->finalizeFinancialNftAuction([
                'token_id' => (int) $auction->nft->token_id,
            ]);
        }

        $auction->status = 'settled';
        $auction->auction_tx_hash = $onChain['tx_hash'] ?? $auction->auction_tx_hash;
        $auction->save();

        $platformFee = $this->percentageOf((string) $auction->current_highest_bid_exa, (int) config('nft.marketplace_fee_bps', 250));
        $this->recordRevenue($auction->nft, $auction->highest_bidder_user_id, 'auction_sale', (string) $auction->current_highest_bid_exa, $platformFee, '0', $onChain['tx_hash'] ?? null, ['auction_id' => $auction->id]);

        return $auction->fresh();
    }

    public function syncBlockchainEvent(array $payload): void
    {
        $event = (string) $payload['event'];
        $tokenId = isset($payload['token_id']) ? (int) $payload['token_id'] : null;
        $txHash = $payload['tx_hash'] ?? null;

        $nft = $tokenId ? Nft::query()->where('token_id', $tokenId)->first() : null;

        if ($nft) {
            $nft->last_event_tx_hash = $txHash;
            $nft->last_synced_at = now();
            if ($event === 'NFTSold' && !empty($payload['buyer_wallet'])) {
                $nft->owner_wallet = (string) $payload['buyer_wallet'];
            }
            if ($event === 'NFTUpgraded') {
                $nft->tier = $payload['tier'] ?? $nft->tier;
                $nft->level = (int) ($payload['level'] ?? $nft->level);
            }
            $nft->save();
        }

        $this->logAudit($nft?->user_id, 'nft_blockchain_event_synced', [
            'event' => $event,
            'token_id' => $tokenId,
            'tx_hash' => $txHash,
            'payload' => $payload,
        ]);
    }

    private function bootstrapUtilityRecords(Nft $nft, User $user, array $payload): void
    {
        switch ($nft->utility_type) {
            case 'staking':
                NftStakingPosition::query()->create([
                    'nft_id' => $nft->id,
                    'user_id' => $user->id,
                    'staked_amount_exa' => (string) ($payload['staked_amount_exa'] ?? '0'),
                    'reward_rate_bps' => (string) ($payload['reward_rate_bps'] ?? '1200'),
                    'platform_commission_bps' => (string) config('nft.staking_commission_bps', 1200),
                    'accumulated_rewards_exa' => '0',
                    'status' => 'active',
                    'started_at' => now(),
                    'metadata' => $payload['staking_metadata'] ?? [],
                ]);
                break;
            case 'fiat_bridge':
                NftFiatProfile::query()->create([
                    'nft_id' => $nft->id,
                    'user_id' => $user->id,
                    'daily_limit_usd' => (string) ($payload['daily_limit_usd'] ?? '1000'),
                    'withdrawal_fee_bps' => (string) config('nft.withdrawal_fee_bps', 120),
                    'spread_bps' => (string) config('nft.fiat_spread_bps', 90),
                    'speed_tier' => $payload['speed_tier'] ?? 'standard',
                    'status' => 'active',
                    'metadata' => $payload['fiat_metadata'] ?? [],
                ]);
                break;
            case 'credit_line':
                NftCreditLine::query()->create([
                    'nft_id' => $nft->id,
                    'user_id' => $user->id,
                    'credit_limit_exa' => (string) ($payload['credit_limit_exa'] ?? '0'),
                    'available_credit_exa' => (string) ($payload['credit_limit_exa'] ?? '0'),
                    'interest_bps' => (string) config('nft.credit_interest_bps', 1800),
                    'liquidation_penalty_bps' => (string) config('nft.credit_liquidation_penalty_bps', 700),
                    'credit_score' => (int) ($payload['credit_score'] ?? 600),
                    'status' => 'active',
                    'metadata' => $payload['credit_metadata'] ?? [],
                ]);
                break;
            default:
                break;
        }
    }

    private function guardOwnership(Nft $nft, User $user, string $walletAddress): void
    {
        if ($nft->user_id !== $user->id) {
            throw new RuntimeException('NFT does not belong to the authenticated user.');
        }

        if ($nft->owner_wallet && strcasecmp($nft->owner_wallet, $walletAddress) !== 0) {
            throw new RuntimeException('Wallet address does not match the current NFT owner.');
        }
    }

    private function verifyBlockchainOwnership(Nft $nft, string $walletAddress): void
    {
        if (!$nft->token_id || !$nft->contract_address || !config('nft.contract_enabled', true)) {
            return;
        }

        $result = $this->blockchain->verifyFinancialNftOwnership([
            'token_id' => (int) $nft->token_id,
            'wallet_address' => $walletAddress,
        ]);

        if (!($result['is_owner'] ?? false)) {
            throw new RuntimeException('Blockchain ownership verification failed.');
        }
    }

    private function utilityConfig(string $utility): array
    {
        $config = config("nft.utilities.{$utility}");
        if (!$config) {
            throw new RuntimeException('Unsupported NFT utility type.');
        }

        return $config;
    }

    private function transformNft(Nft $nft): array
    {
        return [
            'id' => $nft->id,
            'token_id' => $nft->token_id,
            'name' => $nft->name,
            'utility_type' => $nft->utility_type,
            'tier' => $nft->tier,
            'level' => $nft->level,
            'status' => $nft->status,
            'current_value_exa' => $nft->current_value_exa,
            'earnings_generated_exa' => $nft->earnings_generated_exa,
            'benefits' => $nft->benefits ?? [],
            'upgrade_options' => $nft->upgrade_options ?? [],
            'metadata_url' => $nft->metadata_url,
            'collection' => $nft->collection?->name,
            'owner_wallet' => $nft->owner_wallet,
            'listing' => optional($nft->listings()->where('status', 'active')->latest()->first(), fn (NftListing $listing) => [
                'id' => $listing->id,
                'price_exa' => $listing->price_exa,
                'listing_type' => $listing->listing_type,
            ]),
        ];
    }

    private function buildUpgradePrompts(Collection $ownedNfts, Collection $subscriptions): array
    {
        $prompts = [];
        if (!$ownedNfts->contains(fn (Nft $nft) => in_array($nft->utility_type, ['boost', 'fee'], true))) {
            $prompts[] = 'Mint a Boost or Fee NFT to improve staking yield and lower transaction costs.';
        }
        if (!$subscriptions->count()) {
            $prompts[] = 'Upgrade into a premium subscription to unlock AI financial insights and recurring platform value.';
        }
        if (!$ownedNfts->contains(fn (Nft $nft) => $nft->utility_type === 'fiat_bridge')) {
            $prompts[] = 'Unlock Fiat Bridge NFT tiers to raise withdrawal limits and generate spread-based value.';
        }

        return $prompts;
    }

    private function defaultUpgradeOptions(string $utility): array
    {
        return [
            ['label' => 'Tier Upgrade', 'target_tier' => 'pro', 'utility' => $utility],
            ['label' => 'Institutional Upgrade', 'target_tier' => 'institutional', 'utility' => $utility],
        ];
    }

    private function makeMetadataUrl(array $metadata): string
    {
        return 'ipfs://exaearn/' . sha1(json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    private function recordRevenue(?Nft $nft, ?int $userId, string $eventType, string $gross, string $platformRevenue, string $tokenBurn, ?string $txHash, array $metadata = []): void
    {
        NftRevenueEvent::query()->create([
            'nft_id' => $nft?->id,
            'user_id' => $userId,
            'event_type' => $eventType,
            'gross_amount_exa' => $gross,
            'platform_revenue_exa' => $platformRevenue,
            'token_burn_exa' => $tokenBurn,
            'tx_hash' => $txHash,
            'metadata' => $metadata,
        ]);
    }

    private function logAudit(?int $userId, string $action, array $metadata = []): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => request()?->ip(),
            'device' => request()?->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, self::SCALE);
        }

        return number_format(((float) $left + (float) $right), self::SCALE, '.', '');
    }

    private function percentageOf(string $amount, int $bps): string
    {
        if (function_exists('bcmul') && function_exists('bcdiv')) {
            return bcdiv(bcmul($amount, (string) $bps, self::SCALE + 2), '10000', self::SCALE);
        }

        return number_format((((float) $amount * $bps) / 10000), self::SCALE, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, self::SCALE);
        }

        return (float) $left <=> (float) $right;
    }
}
