<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SkillsInstructorPayout;
use App\Models\SkillsMediaAsset;
use App\Models\SkillsOpportunity;
use App\Services\ExaSkillsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExaSkillsAdminController extends Controller
{
    public function __construct(private readonly ExaSkillsService $exaSkills)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->adminOverview()]);
    }

    public function payoutChallengeWinner(Request $request, string $challenge): JsonResponse
    {
        $payload = $request->validate([
            'winner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $escrow = $this->exaSkills->payoutChallengeWinner($request->user(), $challenge, (int) $payload['winner_user_id']);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Challenge winner paid.', 'data' => $escrow]);
    }

    public function reviewCourse(Request $request, string $course): JsonResponse
    {
        $payload = $request->validate(['action' => ['required', 'string'], 'reason' => ['required', 'string']]);
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->reviewCourse($request->user(), $course, $payload['action'], $payload['reason'])]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function media(Request $request, SkillsMediaAsset $asset): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file($this->exaSkills->mediaPath($request->user(), $asset));
    }

    public function approvePayout(Request $request, SkillsInstructorPayout $payout): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->approveInstructorPayout($request->user(), $payout)]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function revokeCredential(Request $request, string $credential): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string']]);
        return response()->json(['success' => true, 'data' => $this->exaSkills->revokeCredential($request->user(), $credential, $payload['reason'])]);
    }

    public function reconciliation(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->exaSkills->reconciliation()]);
    }

    public function createTaxPolicy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'country' => ['nullable', 'string', 'size:2'],
            'entity_type' => ['nullable', 'string', 'max:40'],
            'income_category' => ['nullable', 'string', 'max:80'],
            'payout_asset' => ['nullable', 'string', 'max:20'],
            'outcome' => ['required', 'string', 'max:40'],
            'withholding_rate' => ['nullable', 'numeric', 'min:0'],
            'policy_version' => ['required', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['success' => true, 'data' => $this->exaSkills->createTaxPolicy($request->user(), $payload)], 201);
    }

    public function moderateOpportunity(Request $request, SkillsOpportunity $opportunity): JsonResponse
    {
        $payload = $request->validate(['action' => ['required', 'string'], 'reason' => ['required', 'string']]);
        try {
            return response()->json(['success' => true, 'data' => $this->exaSkills->moderateOpportunity($request->user(), $opportunity, $payload['action'], $payload['reason'])]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
