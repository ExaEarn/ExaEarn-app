<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgriDisbursement;
use App\Models\AgriHarvestSettlement;
use App\Models\AgriProjectMilestone;
use App\Models\AgriReconciliationFinding;
use App\Models\Farmer;
use App\Models\FarmInvestment;
use App\Models\FarmingProject;
use App\Services\AgriTech\AgriDisbursementService;
use App\Services\AgriTech\AgriEvidenceService;
use App\Services\AgriTech\AgriTechReconciliationService;
use App\Services\AgriTech\AgriProjectStateService;
use App\Services\AgriTech\AgriRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AgriTechOperationsController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json(['data' => [
            'farmers' => Farmer::query()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state'),
            'projects' => FarmingProject::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'investments' => FarmInvestment::query()->selectRaw('financial_status, count(*) as total')->groupBy('financial_status')->pluck('total', 'financial_status'),
            'pending_evidence' => DB::table('agri_project_evidence')->where('status', 'PENDING_REVIEW')->count(),
            'pending_disbursements' => AgriDisbursement::query()->whereIn('status', ['PENDING_APPROVAL', 'AWAITING_CHECK'])->count(),
            'open_reconciliation_findings' => AgriReconciliationFinding::query()->where('status', 'OPEN')->count(),
            'harvest_settlements' => AgriHarvestSettlement::query()->count(),
            'public_investment_enabled' => (bool) config('agriculture.public_investment_enabled'),
            'tokenized_investment_enabled' => (bool) config('agriculture.tokenized_investment_enabled'),
        ]]);
    }

    public function evidence(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('agri_project_evidence')->orderByDesc('id')->paginate((int) $request->query('per_page', 50))]);
    }

    public function reviewEvidence(Request $request, int $evidenceId, AgriEvidenceService $service): JsonResponse
    {
        $payload = $request->validate(['decision' => ['required', 'in:APPROVED,REJECTED,REQUEST_INFORMATION,FLAGGED'], 'reason' => ['nullable', 'string', 'max:2000']]);
        try {
            return response()->json(['data' => $service->review($request->user(), $evidenceId, $payload['decision'], $payload['reason'] ?? null)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function createMilestone(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'project_id' => ['required', 'exists:farming_projects,id'], 'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'release_amount' => ['required', 'decimal:0,18', 'gt:0'],
            'asset' => ['required', 'string', 'max:16'], 'target_at' => ['nullable', 'date'], 'evidence_required' => ['nullable', 'boolean'],
        ]);
        $row = AgriProjectMilestone::query()->create(array_merge($payload, ['status' => 'PENDING']));
        return response()->json(['data' => $row], 201);
    }

    public function transitionProject(Request $request, int $projectId, AgriProjectStateService $service): JsonResponse
    {
        $payload = $request->validate(['status' => ['required', 'string', 'max:32'], 'reason' => ['required', 'string', 'max:2000']]);
        try { return response()->json(['data' => $service->transition($request->user(), $projectId, $payload['status'], $payload['reason'])]); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
    }

    public function approveMilestone(Request $request, int $milestoneId): JsonResponse
    {
        $milestone = AgriProjectMilestone::query()->findOrFail($milestoneId);
        if ($milestone->evidence_required && !DB::table('agri_project_evidence')->where('project_id', $milestone->project_id)->where('status', 'APPROVED')->exists()) {
            return response()->json(['message' => 'Approved milestone evidence is required.'], 422);
        }
        $milestone->forceFill(['status' => 'APPROVED', 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
        return response()->json(['data' => $milestone]);
    }

    public function requestDisbursement(Request $request, AgriDisbursementService $service): JsonResponse
    {
        $payload = $request->validate(['milestone_id' => ['required', 'exists:agri_project_milestones,id'], 'farmer_id' => ['required', 'exists:farmers,id'], 'amount' => ['required', 'decimal:0,18', 'gt:0']]);
        $key = (string) $request->header('Idempotency-Key');
        if ($key === '') return response()->json(['message' => 'An idempotency key is required.'], 422);
        try { return response()->json(['data' => $service->request($request->user(), (int) $payload['milestone_id'], (int) $payload['farmer_id'], (string) $payload['amount'], $key)], 201); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
    }

    public function approveDisbursement(Request $request, int $id, AgriDisbursementService $service): JsonResponse
    {
        try { return response()->json(['data' => $service->approve($request->user(), $id)]); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
    }

    public function settleDisbursement(Request $request, int $id, AgriDisbursementService $service): JsonResponse
    {
        try { return response()->json(['data' => $service->checkAndSettle($request->user(), $id)]); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
    }

    public function reconciliation(Request $request, AgriTechReconciliationService $service): JsonResponse
    {
        return response()->json(['data' => $service->reconcile($request->integer('project_id') ?: null)]);
    }

    public function refund(Request $request, int $investmentId, AgriRefundService $service): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        try { return response()->json(['data' => $service->refund($request->user(), $investmentId, $payload['reason'])]); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
    }
}
