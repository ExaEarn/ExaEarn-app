<?php

namespace App\Http\Controllers;

use App\Models\CrowdfundingCampaign;
use App\Models\CrowdfundingComment;
use App\Models\CrowdfundingDocument;
use App\Models\CrowdfundingMilestone;
use App\Services\CrowdfundingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CrowdfundingController extends Controller
{
    public function index(Request $request, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->list($request->query())]);
    }

    public function show(CrowdfundingCampaign $campaign): JsonResponse
    {
        return response()->json(['data' => $campaign->load(['creator.user:id,name', 'milestones', 'updates', 'documents' => fn ($query) => $query->where('visibility', 'PUBLIC')->where('status', 'APPROVED')])]);
    }

    public function creatorDashboard(Request $request, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->creatorDashboard($request->user())]);
    }

    public function backerDashboard(Request $request, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->backerDashboard($request->user())]);
    }

    public function store(Request $request, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate([
            'creator_display_name' => ['nullable', 'string', 'max:120'],
            'classification' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:80'],
            'asset' => ['nullable', 'string', 'max:24'],
            'funding_goal' => ['required', 'numeric', 'gt:0'],
            'minimum_goal' => ['nullable', 'numeric', 'gte:0'],
            'maximum_goal' => ['nullable', 'numeric', 'gte:0'],
            'minimum_pledge' => ['nullable', 'numeric', 'gt:0'],
            'maximum_pledge' => ['nullable', 'numeric', 'gt:0'],
            'funding_model' => ['nullable', 'string'],
            'country' => ['nullable', 'string', 'max:8'],
            'documents' => ['nullable', 'array'],
            'public_media' => ['nullable', 'array'],
        ]);
        try {
            return response()->json(['data' => $crowdfunding->createCampaign($request->user(), $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function submit(CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        abort_unless((int) $campaign->creator->user_id === (int) request()->user()->id, 404);
        return response()->json(['data' => $crowdfunding->transition($campaign, 'SUBMITTED')]);
    }

    public function pledge(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'anonymous_display' => ['nullable', 'boolean']]);
        try {
            return response()->json(['data' => $crowdfunding->pledge($request->user(), $campaign, $payload, $request->header('Idempotency-Key'))], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function milestoneSubmit(Request $request, CrowdfundingMilestone $milestone, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->submitMilestone($milestone, $request->user(), $request->validate(['evidence' => ['nullable', 'array']]))]);
    }

    public function update(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate(['title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string']]);
        return response()->json(['data' => $crowdfunding->publishUpdate($campaign, $request->user(), $payload)], 201);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->crowdfundingPledges()->with('campaign:id,public_id,title,status,asset')->latest()->paginate(20)]);
    }

    public function logs(CrowdfundingCampaign $campaign): JsonResponse
    {
        return response()->json(['data' => $campaign->updates()->latest('published_at')->get()]);
    }

    public function comments(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->comments($campaign, (int) $request->query('per_page', 20))]);
    }

    public function comment(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:crowdfunding_comments,id'],
            'type' => ['nullable', 'string'],
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        return response()->json(['data' => $crowdfunding->createComment($request->user(), $campaign, $payload)], 201);
    }

    public function reportComment(Request $request, CrowdfundingComment $comment, CrowdfundingService $crowdfunding): JsonResponse
    {
        return response()->json(['data' => $crowdfunding->reportComment($request->user(), $comment, $request->validate(['reason' => ['required', 'string']])['reason'])]);
    }

    public function uploadDocument(Request $request, CrowdfundingCampaign $campaign, CrowdfundingService $crowdfunding): JsonResponse
    {
        $payload = $request->validate([
            'document' => ['required', 'file', 'max:20480'],
            'document_type' => ['required', 'string'],
            'visibility' => ['required', 'string'],
        ]);

        try {
            return response()->json(['data' => $crowdfunding->uploadDocument($request->user(), $campaign, $payload['document'], $payload['document_type'], $payload['visibility'])], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function document(Request $request, CrowdfundingDocument $document, CrowdfundingService $crowdfunding): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file($crowdfunding->documentAccess($request->user(), $document));
    }
}
