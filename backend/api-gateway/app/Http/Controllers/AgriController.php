<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AgriService;
use App\Services\AgriTech\AgriHarvestSettlementService;
use App\Services\AgriTech\AgriEvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgriController extends Controller
{
    public function __construct(
        private readonly AgriService $agriService,
        private readonly AgriHarvestSettlementService $harvestSettlements,
    ) {
    }

    public function projects(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->agriService->projects(
                $request->only(['status', 'crop_type', 'location']),
                (int) $request->query('per_page', 20)
            ),
        ]);
    }

    public function project(int $projectId): JsonResponse
    {
        return response()->json(['data' => $this->agriService->projectDashboard($projectId)]);
    }

    public function createProject(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'crop_type' => ['required', 'string', 'max:120'],
            'farm_size' => ['required', 'numeric', 'gt:0'],
            'farm_size_unit' => ['nullable', 'string', 'max:24'],
            'investment_target' => ['required', 'numeric', 'gt:0'],
            'duration' => ['required', 'integer', 'min:1'],
            'duration_unit' => ['nullable', 'string', 'max:24'],
            'expected_yield' => ['required', 'numeric', 'gt:0'],
            'yield_unit' => ['nullable', 'string', 'max:64'],
            'expected_harvest_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:32'],
            'total_shares' => ['required', 'integer', 'min:1'],
            'price_per_share' => ['required', 'numeric', 'gt:0'],
            'ownership_model' => ['nullable', 'string', 'max:32'],
            'token_symbol' => ['nullable', 'string', 'max:32'],
            'verification_documents' => ['nullable', 'array'],
            'share_metadata' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'product_type' => ['nullable', 'string', 'in:' . implode(',', config('agriculture.product_types'))],
            'economic_type' => ['nullable', 'string', 'in:' . implode(',', config('agriculture.economic_types'))],
            'currency' => ['nullable', 'string', 'max:16'],
            'legal_status' => ['nullable', 'string', 'in:PENDING_REVIEW,APPROVED,REJECTED,DISABLED'],
            'verification_status' => ['nullable', 'string', 'in:UNVERIFIED,UNDER_REVIEW,VERIFIED,REJECTED'],
            'public_funding_enabled' => ['nullable', 'boolean'],
            'funding_deadline' => ['nullable', 'date'],
            'risk_disclosures' => ['nullable', 'array'],
            'settlement_policy' => ['nullable', 'array'],
        ]);

        try {
            $project = $this->agriService->createProject($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $project], 201);
    }

    public function invest(Request $request, int $projectId): JsonResponse
    {
        $payload = $request->validate([
            'shares_owned' => ['required', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ]);
        $payload['idempotency_key'] = (string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key', ''));
        $payload['context'] = ['jurisdiction' => $request->user()?->verified_country ?? $request->user()?->residence_country];

        try {
            $investment = $this->agriService->invest($request->user(), $projectId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $investment], 201);
    }

    public function myInvestments(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->agriService->myInvestments($request->user(), (int) $request->query('per_page', 20)),
        ]);
    }

    public function farmers(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->agriService->farmers(
                $request->only(['verification_status']),
                (int) $request->query('per_page', 20)
            ),
        ]);
    }

    public function applyFarmer(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'experience_years' => ['required', 'integer', 'min:0'],
            'identity_documents' => ['nullable', 'array'],
            'equipment_details' => ['nullable', 'array'],
            'geo_metadata' => ['nullable', 'array'],
            'bio' => ['nullable', 'string'],
        ]);

        $farmer = $this->agriService->applyFarmer($request->user(), $payload);

        return response()->json(['data' => $farmer], 201);
    }

    public function reviewFarmer(Request $request, int $farmerId): JsonResponse
    {
        $payload = $request->validate([
            'verification_status' => ['required', 'string', 'in:UNDER_REVIEW,NEEDS_INFORMATION,VERIFIED,APPROVED,SUSPENDED,REJECTED,DEACTIVATED'],
        ]);

        try {
            $farmer = $this->agriService->reviewFarmer($request->user(), $farmerId, (string) $payload['verification_status']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $farmer]);
    }

    public function createLease(Request $request, int $projectId): JsonResponse
    {
        $payload = $request->validate([
            'farmer_id' => ['required', 'integer', 'exists:farmers,id'],
            'investment_id' => ['nullable', 'integer', 'exists:farm_investments,id'],
            'lease_terms' => ['required', 'string'],
            'profit_share' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['nullable', 'string', 'max:32'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $lease = $this->agriService->createLease($request->user(), $projectId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $lease], 201);
    }

    public function addProduceUpdate(Request $request, int $projectId): JsonResponse
    {
        $payload = $request->validate([
            'farmer_id' => ['nullable', 'integer', 'exists:farmers,id'],
            'growth_stage' => ['required', 'string', 'max:64'],
            'update_description' => ['required', 'string'],
            'images' => ['nullable', 'array'],
            'geo_metadata' => ['nullable', 'array'],
            'reported_yield' => ['nullable', 'numeric', 'gte:0'],
            'recorded_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $update = $this->agriService->addProduceUpdate($request->user(), $projectId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $update], 201);
    }

    public function produceFeed(int $projectId): JsonResponse
    {
        return response()->json(['data' => $this->agriService->produceFeed($projectId)]);
    }

    public function submitEvidence(Request $request, int $projectId, AgriEvidenceService $service): JsonResponse
    {
        $payload = $request->validate([
            'farmer_id' => ['nullable', 'integer', 'exists:farmers,id'],
            'evidence_type' => ['required', 'string', 'in:IDENTITY,LAND_TITLE,LAND_LEASE,FARM_INSPECTION,MILESTONE,HARVEST,HARVEST_REVENUE,INSURANCE,SALE_RECEIPT,WAREHOUSE'],
            'source_type' => ['required', 'string', 'in:PRIVATE_DOCUMENT,MANUAL_INSPECTION,PARTNER_FEED,IOT_ORACLE,MARKET_RECEIPT,WAREHOUSE_EVIDENCE'],
            'storage_reference' => ['nullable', 'string', 'max:512'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);
        try {
            return response()->json(['data' => $service->submit($request->user(), $projectId, $payload)], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function queueSettlement(Request $request, int $projectId): JsonResponse
    {
        $payload = $request->validate([
            'gross_revenue' => ['required', 'numeric', 'gte:0'],
            'verified_costs' => ['nullable', 'numeric', 'gte:0'],
            'period_key' => ['required', 'string', 'max:64'],
            'revenue_source_type' => ['required', 'string', 'in:PRODUCE_BUYER,MARKETPLACE,OFFTAKE_PARTNER,EXTERNAL_SETTLEMENT,EXAPAY,FIAT'],
            'revenue_reference' => ['required', 'string', 'max:255'],
            'evidence_id' => ['required', 'integer', 'exists:agri_project_evidence,id'],
            'asset' => ['nullable', 'string', 'max:16'],
            'idempotency_key' => ['nullable', 'string', 'max:180'],
        ]);
        $payload['idempotency_key'] = (string) ($request->header('Idempotency-Key') ?: ($payload['idempotency_key'] ?? ''));
        if ($payload['idempotency_key'] === '') {
            return response()->json(['message' => 'An idempotency key is required.'], 422);
        }

        try {
            $settlement = $this->harvestSettlements->settle($request->user(), $projectId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $settlement], 201);
    }
}
