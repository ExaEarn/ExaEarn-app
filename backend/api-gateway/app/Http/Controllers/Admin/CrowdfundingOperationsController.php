<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CrowdfundingCampaign;
use App\Models\CrowdfundingComment;
use App\Models\CrowdfundingCreator;
use App\Models\CrowdfundingDocument;
use App\Models\CrowdfundingMilestone;
use App\Models\CrowdfundingPledge;
use App\Models\CrowdfundingPayout;
use App\Models\CrowdfundingReconciliationIncident;
use App\Models\CrowdfundingRefundBatch;
use App\Services\CrowdfundingReconciliationService;
use App\Services\CrowdfundingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CrowdfundingOperationsController extends Controller
{
    public function overview(CrowdfundingReconciliationService $reconciliation, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => [
            'campaigns' => CrowdfundingCampaign::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'pledges' => CrowdfundingPledge::query()->count(),
            'escrowed' => CrowdfundingPledge::query()->where('status', 'HELD_IN_ESCROW')->selectRaw('asset, sum(amount) as total')->groupBy('asset')->pluck('total', 'asset'),
            'creator_payables' => CrowdfundingPayout::query()->whereIn('status', ['PAYOUT_PENDING', 'PROCESSING'])->selectRaw('asset, sum(amount) as total')->groupBy('asset')->pluck('total', 'asset'),
            'refund_liabilities' => CrowdfundingPledge::query()->whereIn('status', ['REFUND_PENDING'])->selectRaw('asset, sum(amount) as total')->groupBy('asset')->pluck('total', 'asset'),
            'operations' => $crowdfunding->operationsStatus(),
            'reconciliation' => $reconciliation->run(),
        ]]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        return response()->json(['data' => CrowdfundingCampaign::query()->with('creator.user:id,name,email')->latest()->paginate((int) $request->query('per_page', 30))]);
    }

    public function creators(Request $request): JsonResponse
    {
        return response()->json(['data' => CrowdfundingCreator::query()->with('user:id,name,email')->latest()->paginate((int) $request->query('per_page', 30))]);
    }

    public function review(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['action' => ['required', 'string'], 'reason' => ['nullable', 'string']]);
        $map = ['APPROVE' => 'APPROVED', 'REQUEST_INFORMATION' => 'NEEDS_INFORMATION', 'REJECT' => 'REJECTED', 'HOLD' => 'UNDER_REVIEW', 'SUSPEND' => 'SUSPENDED'];
        try {
            return response()->json(['data' => $crowdfunding->transition($campaign, $map[strtoupper($payload['action'])] ?? strtoupper($payload['action']), $request->user(), ['reason' => $payload['reason'] ?? null])]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function milestone(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['sequence' => ['required', 'integer', 'min:1'], 'title' => ['required', 'string'], 'description' => ['nullable', 'string'], 'target_amount' => ['nullable', 'numeric'], 'release_percentage' => ['nullable', 'numeric']]);
        return response()->json(['data' => $crowdfunding->createMilestone($campaign, $payload)], 201);
    }

    public function reviewMilestone(Request $request, CrowdfundingMilestone $milestone, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->reviewMilestone($milestone, $request->user(), $request->validate(['action' => ['required', 'string']])['action'])]);
    }

    public function releaseMilestone(Request $request, CrowdfundingMilestone $milestone, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['checker_admin_id' => ['required', 'integer', 'exists:admins,id']]);
        try {
            return response()->json(['data' => $crowdfunding->releaseMilestone($milestone, $request->user(), Admin::query()->findOrFail($payload['checker_admin_id']))], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function refund(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        try {
            return response()->json(['data' => $crowdfunding->refundCampaign($campaign, $request->validate(['reason' => ['required', 'string']])['reason'])], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reconciliation(CrowdfundingReconciliationService $reconciliation): JsonResponse
    {
        return response()->json(['data' => $reconciliation->run()]);
    }

    public function records(): JsonResponse
    {
        return response()->json(['data' => [
            'pledges' => CrowdfundingPledge::query()->latest()->limit(100)->get(),
            'payouts' => CrowdfundingPayout::query()->latest()->limit(100)->get(),
            'refund_batches' => CrowdfundingRefundBatch::query()->latest()->limit(100)->get(),
            'incidents' => CrowdfundingReconciliationIncident::query()->latest()->limit(100)->get(),
            'comments' => CrowdfundingComment::query()->with('user:id,name,email')->latest()->limit(100)->get(),
            'documents' => CrowdfundingDocument::query()->with('owner:id,name,email')->latest()->limit(100)->get(),
        ]]);
    }

    public function moderateComment(Request $request, CrowdfundingComment $comment, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['status' => ['required', 'string'], 'reason' => ['required', 'string']]);
        return response()->json(['data' => $crowdfunding->moderateComment($request->user(), $comment, $payload['status'], $payload['reason'])]);
    }

    public function reviewDocument(Request $request, CrowdfundingDocument $document, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['status' => ['required', 'string'], 'reason' => ['required', 'string']]);
        return response()->json(['data' => $crowdfunding->reviewDocument($request->user(), $document, $payload['status'], $payload['reason'])]);
    }

    public function document(Request $request, CrowdfundingDocument $document, CrowdfundingService $crowdfunding): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file($crowdfunding->documentAccess($request->user(), $document));
    }

    public function operations(CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->operationsStatus()]);
    }

    public function updateOperations(Request $request, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['key' => ['required', 'string'], 'value' => ['required', 'array']]);
        try {
            return response()->json(['data' => $crowdfunding->updateOperationsSetting($request->user(), $payload['key'], $payload['value'])]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function assignReview(Request $request, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate([
            'entity_type' => ['required', 'string'],
            'entity_id' => ['required', 'integer'],
            'assignee_admin_id' => ['required', 'integer', 'exists:admins,id'],
            'reason' => ['required', 'string'],
        ]);

        try {
            return response()->json(['data' => $crowdfunding->assignReview($request->user(), $payload['entity_type'], (int) $payload['entity_id'], (int) $payload['assignee_admin_id'], $payload['reason'])], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
