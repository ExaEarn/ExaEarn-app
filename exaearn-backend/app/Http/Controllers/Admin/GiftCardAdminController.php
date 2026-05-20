<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardSubmission;
use App\Services\GiftCard\GiftCardSellService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GiftCardAdminController extends Controller
{
    public function __construct(
        private readonly GiftCardSellService $giftCardSellService
    ) {
    }

    /**
     * GET /api/admin/giftcard/submissions
     * Get all gift card submissions for admin review.
     */
    public function submissions(Request $request): JsonResponse
    {
        $query = GiftCardSubmission::with(['user:id,email,name', 'approver:id,email,name'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by risk level
        if ($request->has('risk_level')) {
            $query->where('metadata->fraud_risk_level', $request->query('risk_level'));
        }

        // Filter by brand
        if ($request->has('brand')) {
            $query->where('brand', $request->query('brand'));
        }

        $submissions = $query->paginate($request->query('per_page', 20));

        // Add risk analysis to each submission
        $submissions->getCollection()->transform(function ($submission) {
            $metadata = $submission->metadata ?? [];
            return [
                'id' => $submission->id,
                'user' => $submission->user,
                'brand' => $submission->brand,
                'card_value' => $submission->card_value,
                'currency' => $submission->currency,
                'payout_amount' => $submission->payout_amount,
                'status' => $submission->status,
                'risk_level' => $metadata['fraud_risk_level'] ?? 'UNKNOWN',
                'risk_score' => $metadata['fraud_risk_score'] ?? '0.00',
                'auto_decision' => $metadata['auto_decision'] ?? 'review',
                'fraud_flags' => $metadata['fraud_flags'] ?? [],
                'created_at' => $submission->created_at,
                'approved_at' => $submission->approved_at,
                'approver' => $submission->approver,
            ];
        });

        return response()->json([
            'data' => $submissions->items(),
            'pagination' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/giftcard/submissions/{id}
     * Get detailed submission info for admin.
     */
    public function submissionDetails(int $id): JsonResponse
    {
        $submission = GiftCardSubmission::with(['user:id,email,name', 'approver:id,email,name'])
            ->findOrFail($id);

        return response()->json([
            'data' => $submission,
        ]);
    }

    /**
     * POST /api/admin/giftcard/submissions/{id}/approve
     * Admin approve a submission.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $submission = $this->giftCardSellService->approveSubmission($id, $request->user()->id);

            Log::info('Admin approved gift card submission', [
                'submission_id' => $id,
                'admin_id' => $request->user()->id,
                'notes' => $payload['notes'] ?? null,
            ]);

            return response()->json([
                'message' => 'Submission approved and wallet credited.',
                'data' => $submission,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve gift card submission', [
                'submission_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/admin/giftcard/submissions/{id}/reject
     * Admin reject a submission.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $submission = $this->giftCardSellService->rejectSubmission($id, $request->user()->id, $payload['reason']);

            Log::info('Admin rejected gift card submission', [
                'submission_id' => $id,
                'admin_id' => $request->user()->id,
                'reason' => $payload['reason'],
            ]);

            return response()->json([
                'message' => 'Submission rejected.',
                'data' => $submission,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject gift card submission', [
                'submission_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/admin/giftcard/stats
     * Get gift card statistics for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'pending_submissions' => GiftCardSubmission::where('status', 'pending')->count(),
            'verifying_submissions' => GiftCardSubmission::where('status', 'verifying')->count(),
            'approved_today' => GiftCardSubmission::where('status', 'approved')
                ->whereDate('approved_at', today())->count(),
            'rejected_today' => GiftCardSubmission::where('status', 'rejected')
                ->whereDate('approved_at', today())->count(),
            'total_volume_today' => GiftCardSubmission::where('status', 'approved')
                ->whereDate('approved_at', today())
                ->sum('payout_amount'),
            'top_brands' => GiftCardSubmission::selectRaw('brand, COUNT(*) as count')
                ->where('status', 'approved')
                ->groupBy('brand')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}