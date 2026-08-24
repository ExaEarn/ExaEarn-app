<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalApplication;
use App\Models\InstitutionalAuditEvent;
use App\Models\InstitutionalSubaccount;
use App\Models\InstitutionalTransferRequest;
use App\Services\InstitutionalService;
use App\Services\VipTierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InstitutionalOperationsController extends Controller
{
    public function __construct(private readonly InstitutionalService $institutions, private readonly VipTierService $vip)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'pending_applications' => InstitutionalApplication::query()->whereNotIn('state', ['ACTIVE', 'CLOSED'])->count(),
            'active_institutions' => InstitutionalAccount::query()->where('status', 'ACTIVE')->count(),
            'subaccounts' => InstitutionalSubaccount::query()->count(),
            'pending_transfer_approvals' => InstitutionalTransferRequest::query()->where('status', 'PENDING_APPROVAL')->count(),
            'restricted_accounts' => InstitutionalAccount::query()->whereIn('status', ['RESTRICTED', 'SUSPENDED'])->count(),
        ]]);
    }

    public function applications(Request $request): JsonResponse
    {
        return response()->json(['data' => InstitutionalApplication::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function transition(Request $request, string $uuid): JsonResponse
    {
        $payload = $request->validate(['state' => ['required', 'string'], 'reason' => ['required', 'string', 'max:1000']]);
        $application = InstitutionalApplication::query()->where('application_uuid', $uuid)->firstOrFail();

        return $this->handle(fn () => $this->institutions->transitionApplication($this->admin($request), $application, strtoupper($payload['state']), $payload['reason'], $request));
    }

    public function activate(Request $request, string $uuid): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $application = InstitutionalApplication::query()->where('application_uuid', $uuid)->firstOrFail();

        return $this->handle(fn () => $this->institutions->activate($this->admin($request), $application, $payload['reason'], $request), 201);
    }

    public function institutions(Request $request): JsonResponse
    {
        return response()->json(['data' => InstitutionalAccount::query()->with(['subaccounts', 'memberships.role'])->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function creditSubaccount(Request $request, string $uuid): JsonResponse
    {
        $payload = $request->validate(['asset' => ['required', 'string', 'max:24'], 'amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $subaccount = InstitutionalSubaccount::query()->where('subaccount_uuid', $uuid)->firstOrFail();

        return $this->handle(fn () => $this->institutions->adminCreditSubaccount($this->admin($request), $subaccount, strtoupper($payload['asset']), (string) $payload['amount'], $payload['reason'], $request), 201);
    }

    public function feeProfile(Request $request): JsonResponse
    {
        $payload = $request->validate(['name' => ['required', 'string', 'max:120'], 'rules' => ['required', 'array'], 'reason' => ['required', 'string', 'max:1000'], 'effective_at' => ['nullable', 'date']]);

        return $this->handle(fn () => $this->institutions->createFeeProfile($this->admin($request), $payload, $request), 201);
    }

    public function vip(Request $request, int $institutionId): JsonResponse
    {
        $payload = $request->validate([
            'spot_volume_30d' => ['nullable', 'numeric', 'gte:0'],
            'futures_volume_30d' => ['nullable', 'numeric', 'gte:0'],
            'average_balance' => ['nullable', 'numeric', 'gte:0'],
            'manual_override_tier' => ['nullable', 'string'],
            'contractual_tier' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $institution = InstitutionalAccount::query()->findOrFail($institutionId);

        return $this->handle(fn () => $this->vip->updateTier($institution, $payload, $this->admin($request), $payload['manual_override_tier'] ?? null, $payload['contractual_tier'] ?? null, $payload['reason']), 201);
    }

    public function restrict(Request $request, int $institutionId): JsonResponse
    {
        $payload = $request->validate(['status' => ['required', 'string', 'in:ACTIVE,RESTRICTED,SUSPENDED,CLOSED'], 'reason' => ['required', 'string', 'max:1000']]);
        $institution = InstitutionalAccount::query()->findOrFail($institutionId);
        $before = $institution->toArray();
        $institution->forceFill(['status' => $payload['status']])->save();
        $this->institutions->audit($institution->id, null, 'admin', $this->admin($request)->id, 'institution.status.changed', 'institutional_account', $institution->id, $before, $institution->fresh()->toArray(), $payload['reason'], $request);

        return response()->json(['data' => $institution->fresh()]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => InstitutionalAuditEvent::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin || ! $admin->hasPermission('institutional.manage')) {
            throw new RuntimeException('Institutional admin permission is required.');
        }

        return $admin;
    }

    private function handle(\Closure $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
