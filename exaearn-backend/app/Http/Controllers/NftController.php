<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NftController extends Controller
{
    public function __construct(private readonly NftService $nftService)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->nftService->dashboard($request->user()),
        ]);
    }

    public function collections(): JsonResponse
    {
        return response()->json([
            'data' => $this->nftService->collections()->values(),
        ]);
    }

    public function marketplace(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->nftService->marketplace($request->only(['utility_type', 'phase']))->values(),
        ]);
    }

    public function myNfts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->nftService->myNfts($request->user())->values(),
        ]);
    }

    public function createCollection(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'utility_type' => ['required', 'string', 'max:50'],
            'creator_wallet' => ['nullable', 'string', 'max:255'],
            'royalty_percentage' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $collection = $this->nftService->createCollection($payload);

        return response()->json([
            'message' => 'NFT collection created.',
            'data' => $collection,
        ], 201);
    }

    public function mint(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'utility_type' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120'],
            'wallet_address' => ['required', 'string', 'max:255'],
            'tier' => ['nullable', 'string', 'max:50'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'creator_wallet' => ['nullable', 'string', 'max:255'],
            'collection_name' => ['nullable', 'string', 'max:120'],
            'royalty_percentage' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'current_value_exa' => ['nullable', 'numeric', 'gte:0'],
            'financial_profile' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        $nft = $this->nftService->mint($request->user(), $payload);

        return response()->json([
            'message' => 'Financial NFT minted.',
            'data' => $nft,
        ], 201);
    }

    public function upgrade(Request $request, int $nftId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:255'],
            'target_tier' => ['nullable', 'string', 'max:50'],
            'target_level' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $nft = $this->nftService->upgrade($request->user(), $nftId, $payload);

        return response()->json([
            'message' => 'NFT upgraded.',
            'data' => $nft,
        ]);
    }

    public function subscribe(Request $request, int $nftId): JsonResponse
    {
        $payload = $request->validate([
            'plan' => ['required', 'string', 'max:50'],
            'wallet_address' => ['required', 'string', 'max:255'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $subscription = $this->nftService->subscribe($request->user(), $nftId, $payload);

        return response()->json([
            'message' => 'NFT subscription activated.',
            'data' => $subscription,
        ], 201);
    }

    public function createListing(Request $request, int $nftId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:255'],
            'price_exa' => ['required', 'numeric', 'gt:0'],
            'listing_type' => ['nullable', 'string', 'in:fixed_price,auction'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $listing = $this->nftService->createListing($request->user(), $nftId, $payload);

        return response()->json([
            'message' => 'NFT listed for sale.',
            'data' => $listing,
        ], 201);
    }

    public function buyListing(Request $request, int $listingId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:255'],
            'buyer_wallet' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = $this->nftService->buyListing($request->user(), $listingId, $payload);

        return response()->json([
            'message' => 'NFT purchased.',
            'data' => $sale,
        ], 201);
    }

    public function createAuction(Request $request, int $nftId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:255'],
            'starting_price_exa' => ['required', 'numeric', 'gt:0'],
            'reserve_price_exa' => ['nullable', 'numeric', 'gte:0'],
            'ends_at' => ['required', 'date'],
        ]);

        $auction = $this->nftService->createAuction($request->user(), $nftId, $payload);

        return response()->json([
            'message' => 'NFT auction created.',
            'data' => $auction,
        ], 201);
    }

    public function bid(Request $request, int $auctionId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:255'],
            'bid_amount_exa' => ['required', 'numeric', 'gt:0'],
        ]);

        $auction = $this->nftService->placeBid($request->user(), $auctionId, $payload);

        return response()->json([
            'message' => 'Bid placed.',
            'data' => $auction,
        ], 201);
    }

    public function finalizeAuction(int $auctionId): JsonResponse
    {
        $auction = $this->nftService->finalizeAuction($auctionId);

        return response()->json([
            'message' => 'Auction finalized.',
            'data' => $auction,
        ]);
    }
}
