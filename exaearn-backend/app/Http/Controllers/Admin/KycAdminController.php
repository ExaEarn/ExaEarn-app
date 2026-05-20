<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Kyc\NotifyJob;
use App\Models\KycVerification;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycAdminController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function flagged(): JsonResponse
    {
        return response()->json([
            'data' => KycVerification::query()->where('status', 'flagged')->latest()->paginate(50),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $kyc = KycVerification::query()->with(['user', 'approver'])->findOrFail($id);

        return response()->json(['data' => $kyc]);
    }

    public function approve(Request $request): JsonResponse
    {
        $payload = $request->validate(['kyc_id' => ['required', 'integer', 'exists:kyc_verifications,id'], 'note' => ['nullable', 'string', 'max:1000']]);

        $kyc = KycVerification::query()->with('user')->where('status', 'flagged')->findOrFail((int) $payload['kyc_id']);
        $kyc->status = 'approved';
        $kyc->approved_by = $request->user()->id;
        $kyc->review_note = $payload['note'] ?? null;
        $kyc->save();

        $kyc->user->forceFill(['kyc_level' => $kyc->level, 'kyc_verified_at' => now()])->save();
        $this->auditService->log($kyc->user_id, 'kyc_manual_approved', ['kyc_id' => $kyc->id, 'admin_id' => $request->user()->id]);
        NotifyJob::dispatch($kyc->user_id, 'kyc.approved', 'KYC Approved', 'Your KYC has been approved.');

        return response()->json(['message' => 'KYC approved.', 'data' => $kyc]);
    }

    public function reject(Request $request): JsonResponse
    {
        $payload = $request->validate(['kyc_id' => ['required', 'integer', 'exists:kyc_verifications,id'], 'note' => ['required', 'string', 'max:1000']]);

        $kyc = KycVerification::query()->where('status', 'flagged')->findOrFail((int) $payload['kyc_id']);
        $kyc->status = 'rejected';
        $kyc->approved_by = $request->user()->id;
        $kyc->review_note = $payload['note'];
        $kyc->save();

        $this->auditService->log($kyc->user_id, 'kyc_manual_rejected', ['kyc_id' => $kyc->id, 'admin_id' => $request->user()->id, 'note' => $payload['note']]);
        NotifyJob::dispatch($kyc->user_id, 'kyc.rejected', 'KYC Rejected', 'Your KYC was rejected. Please resubmit your documents.');

        return response()->json(['message' => 'KYC rejected.', 'data' => $kyc]);
    }
}
