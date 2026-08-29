<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Nft;
use App\Models\NftAuction;
use App\Models\NftChainTransaction;
use App\Models\NftListing;
use App\Models\NftMediaAsset;
use App\Models\NftReconciliationBreak;
use App\Models\NftReport;
use App\Models\NftSale;
use App\Services\NftMediaService;
use App\Services\NftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NftOperationsController extends Controller
{
    public function overview(NftService $nfts, NftMediaService $media): JsonResponse
    {
        return response()->json(['data' => [
            'collections' => [
                'assets' => Nft::query()->count(),
                'pending_mints' => Nft::query()->whereIn('mint_status', ['PENDING', 'SUBMITTED', 'CONFIRMING'])->count(),
                'manual_review' => Nft::query()->whereIn('status', ['manual_review'])->count(),
                'reported' => Nft::query()->where('moderation_status', 'REPORTED')->count(),
            ],
            'marketplace' => [
                'active_listings' => NftListing::query()->where('status', 'active')->count(),
                'suspended_listings' => NftListing::query()->where('status', 'suspended')->count(),
                'sales' => NftSale::query()->count(),
                'auctions' => NftAuction::query()->count(),
            ],
            'chain' => [
                'pending' => NftChainTransaction::query()->whereIn('status', ['PENDING', 'SUBMITTED', 'CONFIRMING', 'PENDING_PROVIDER_CONFIGURATION'])->count(),
                'confirmed' => NftChainTransaction::query()->where('status', 'CONFIRMED')->count(),
                'reorg_pending' => NftChainTransaction::query()->where('status', 'REORG_PENDING')->count(),
            ],
            'media' => [
                'total' => NftMediaAsset::query()->count(),
                'ready' => NftMediaAsset::query()->where('status', 'READY')->count(),
                'processing' => NftMediaAsset::query()->where('processing_status', 'PROCESSING')->count(),
                'failed' => NftMediaAsset::query()->where('processing_status', 'FAILED')->count(),
                'quarantined' => NftMediaAsset::query()->where('status', 'QUARANTINED')->orWhere('processing_status', 'QUARANTINED')->count(),
                'private' => NftMediaAsset::query()->where('visibility', 'PRIVATE')->count(),
                'storage_health' => $media->health(),
            ],
            'reconciliation' => $nfts->reconciliation(),
        ]]);
    }

    public function reports(Request $request): JsonResponse
    {
        return response()->json(['data' => NftReport::query()
            ->with(['nft:id,name,nft_uuid,moderation_status,status', 'listing:id,status,price_exa'])
            ->latest()
            ->paginate((int) $request->query('per_page', 30))]);
    }

    public function reconciliation(NftService $nfts): JsonResponse
    {
        return response()->json(['data' => [
            'run' => $nfts->reconciliation(),
            'incidents' => NftReconciliationBreak::query()->latest()->limit(100)->get(),
        ]]);
    }

    public function media(Request $request): JsonResponse
    {
        return response()->json(['data' => NftMediaAsset::query()
            ->with(['nft:id,name,nft_uuid,status,moderation_status', 'collection:id,name,slug'])
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', strtoupper($status)))
            ->when($request->query('processing_status'), fn ($query, string $status) => $query->where('processing_status', strtoupper($status)))
            ->latest()
            ->paginate((int) $request->query('per_page', 30))]);
    }

    public function storageHealth(NftMediaService $media): JsonResponse
    {
        return response()->json(['data' => $media->health()]);
    }

    public function mediaReconciliation(NftMediaService $media): JsonResponse
    {
        return response()->json(['data' => $media->reconciliation()]);
    }

    public function reviewMedia(Request $request, NftMediaAsset $mediaAsset, NftMediaService $media): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['required', 'string'],
        ]);

        try {
            return response()->json(['data' => $media->adminTransition($mediaAsset, $payload['status'], $request->user(), $payload['reason'])]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reviewReport(Request $request, NftReport $report): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['required', 'string'],
        ]);
        $status = strtoupper($payload['status']);
        if (! in_array($status, ['REVIEWING', 'ESCALATED', 'RESOLVED', 'DISMISSED'], true)) {
            throw new RuntimeException('Unsupported NFT report status.');
        }

        $report->update([
            'status' => $status,
            'evidence' => array_merge($report->evidence ?? [], [
                'review' => [
                    'admin_user_id' => $request->user()?->id,
                    'reason' => $payload['reason'],
                    'reviewed_at' => now()->toISOString(),
                ],
            ]),
        ]);

        if ($status === 'DISMISSED' && $report->nft_id) {
            Nft::query()->whereKey($report->nft_id)->where('moderation_status', 'REPORTED')->update(['moderation_status' => 'APPROVED']);
        }

        return response()->json(['data' => $report->fresh()]);
    }
}
