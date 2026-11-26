<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ExaPayOfflineDevice;
use App\Models\ExaPayOfflineInstrument;
use App\Models\ExaPayOfflineReserve;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Models\ExaPayOfflineTransferSession;
use App\Models\ExaPayOfflineReconciliationClaim;
use App\Models\ExaPayOfflineRedemption;
use App\Models\ExaPayOfflineSecurityControl;
use App\Models\ExaPayOfflineIssuerKey;
use App\Models\ExaPayOfflineFraudCase;
use App\Models\ExaPayOfflineAdminChangeApproval;
use App\Models\User;
use App\Domain\ExaPay\Offline\OfflineRiskPolicyContract;
use App\Services\AdminAuditService;
use App\Services\ExaPay\Offline\OfflineInstrumentCrypto;
use App\Services\ExaPay\Offline\OfflineStoredValueService;
use App\Services\ExaPay\Offline\OfflineReconciliationClaimService;
use App\Services\ExaPay\Offline\OfflineRedemptionService;
use App\Services\ExaPay\Offline\OfflineDeviceTrustService;
use App\Services\ExaPay\Offline\OfflineExposureService;
use App\Services\ExaPay\Offline\OfflineEnvironmentGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ExaPayOfflineOperationsController extends Controller
{
    public function overview(OfflineStoredValueService $offline): JsonResponse
    {
        $this->assertNonProduction();
        $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->first();
        $controls = ExaPayOfflineSecurityControl::query()->whereKey(1)->first();
        $capabilities = $offline->capabilities();

        return response()->json(['data' => [
            'asset' => 'SIMUSDT', 'simulation_only' => true,
            'reserved_minor' => (int) ($reserve?->reserved_minor ?? 0),
            'outstanding_liability_minor' => (int) ($reserve?->outstanding_liability_minor ?? 0),
            'issued_minor' => (int) ($reserve?->issued_minor ?? 0),
            'redeemed_minor' => (int) ($reserve?->redeemed_minor ?? 0),
            'extinguished_minor' => (int) ($reserve?->extinguished_minor ?? 0),
            'accounting_conserved' => (int) ($reserve?->issued_minor ?? 0) === (int) ($reserve?->outstanding_liability_minor ?? 0) + (int) ($reserve?->redeemed_minor ?? 0) + (int) ($reserve?->extinguished_minor ?? 0),
            'reserve_liability_balanced' => (int) ($reserve?->reserved_minor ?? 0) === (int) ($reserve?->outstanding_liability_minor ?? 0),
            'active_devices' => ExaPayOfflineDevice::query()->where('status', 'active')->count(),
            'suspended_devices' => ExaPayOfflineDevice::query()->where('status', 'suspended')->count(),
            'outstanding_instruments' => ExaPayOfflineInstrument::query()->whereNotIn('status', ['redeemed', 'failed'])->count(),
            'offline_value_outputs' => \App\Models\ExaPayOfflineValueOutput::query()->whereIn('status', ['available', 'locked', 'quarantined'])->count(),
            'verified_transfers' => ExaPayOfflineTransferSession::query()->where('status', 'acknowledged')->count(),
            'quarantined_transfers' => ExaPayOfflineTransferSession::query()->where('status', 'quarantined')->count(),
            'claims_pending' => ExaPayOfflineReconciliationClaim::query()->whereIn('status', ['SUBMITTED', 'VALIDATING', 'EVIDENCE_INCOMPLETE', 'PENDING', 'REDEMPTION_PENDING', 'RECOVERY_REQUIRED'])->count(),
            'claims_in_conflict' => ExaPayOfflineReconciliationClaim::query()->where('status', 'CONFLICT_DETECTED')->count(),
            'claims_rejected' => ExaPayOfflineReconciliationClaim::query()->where('status', 'REJECTED')->count(),
            'redemptions_completed' => ExaPayOfflineRedemption::query()->where('status', 'REDEEMED')->count(),
            'redemptions_recovery_required' => ExaPayOfflineRedemption::query()->where('status', 'RECOVERY_REQUIRED')->count(),
            'redemption_enabled' => (bool) $capabilities['redemption_enabled'], 'real_funds_enabled' => false,
            'issuance_suspended' => $controls?->issuance_suspended ?? true,
            'redemption_suspended' => $controls?->redemption_suspended ?? true,
            'trust_epoch' => $controls?->trust_epoch,
            'fraud_cases_open' => ExaPayOfflineFraudCase::query()->whereIn('status', ['OPEN', 'INVESTIGATING'])->count(),
            'compromised_issuer_keys' => ExaPayOfflineIssuerKey::query()->where('status', 'COMPROMISED')->count(),
            'verified_hardware_devices' => ExaPayOfflineDevice::query()->where('trust_class', 'VERIFIED_HARDWARE')->where('attestation_status', 'VERIFIED')->count(),
        ]]);
    }

    public function devices(): JsonResponse
    {
        $this->assertNonProduction();

        return response()->json(['data' => ExaPayOfflineDevice::query()->latest('id')->paginate(50)]);
    }

    public function suspendDevice(Request $request, int $device, OfflineStoredValueService $service, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:160']]);
        $admin = $this->admin($request);
        $suspended = $service->suspendDevice($device, $payload['reason']);
        $audit->log($admin, 'exapay.offline.device.suspended', ['device_id' => $suspended->id, 'device_uuid' => $suspended->device_uuid, 'reason' => $payload['reason']], $request);

        return response()->json(['data' => ['device_uuid' => $suspended->device_uuid, 'status' => $suspended->status, 'suspended_at' => $suspended->suspended_at?->toIso8601String()]]);
    }

    public function issuances(): JsonResponse
    {
        $this->assertNonProduction();

        return response()->json(['data' => ExaPayOfflineInstrument::query()->with('device:id,device_uuid,status')->latest('id')->paginate(50)]);
    }

    public function transfers(): JsonResponse
    {
        $this->assertNonProduction();
        return response()->json(['data' => ExaPayOfflineTransferSession::query()->latest('id')->paginate(100)]);
    }

    public function claims(Request $request, OfflineReconciliationClaimService $service): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['status' => ['nullable', 'in:SUBMITTED,VALIDATING,EVIDENCE_INCOMPLETE,CONFLICT_DETECTED,PENDING,VERIFIED,REDEMPTION_PENDING,REDEEMED,REJECTED,RECOVERY_REQUIRED'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        return response()->json(['data' => $service->adminList($payload['status'] ?? null, (int) ($payload['per_page'] ?? 50))]);
    }

    public function redemptions(Request $request, OfflineRedemptionService $service): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['status' => ['nullable', 'in:REDEMPTION_PENDING,REDEEMED,RECOVERY_REQUIRED'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        return response()->json(['data' => $service->adminList($payload['status'] ?? null, (int) ($payload['per_page'] ?? 50))]);
    }

    public function retryClaim(Request $request, string $claimUuid, OfflineReconciliationClaimService $service, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $claim = ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $claimUuid)->first();
        abort_unless($claim, 404);
        $admin = $this->admin($request);
        $result = $service->evaluate(User::query()->findOrFail($claim->user_id), $claimUuid);
        $audit->log($admin, 'exapay.offline.claim.retry', ['claim_uuid' => $claimUuid, 'result_status' => $result['status']], $request);
        return response()->json(['data' => $result]);
    }

    public function recoverRedemption(Request $request, string $redemptionUuid, OfflineRedemptionService $service, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $admin = $this->admin($request);
        $result = $service->recover($redemptionUuid);
        $audit->log($admin, 'exapay.offline.redemption.recovery_attempted', [
            'redemption_uuid' => $redemptionUuid, 'result_status' => $result['status'], 'automatic_reposting' => false,
        ], $request);
        return response()->json(['data' => $result]);
    }

    public function riskPolicy(Request $request, AdminAuditService $audit, OfflineInstrumentCrypto $crypto, OfflineDeviceTrustService $trust): JsonResponse
    {
        $this->assertNonProduction();
        $policy = ExaPayOfflineRiskPolicy::query()->firstOrFail();
        if ($request->isMethod('get')) {
            return response()->json(['data' => $policy]);
        }
        $admin = $this->admin($request);
        $payload = $request->validate([
            'enabled' => ['required', 'boolean'], 'max_amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'max_outstanding_per_user_minor' => ['required', 'integer', 'min:1', 'max:5000000000'],
            'max_devices_per_user' => ['required', 'integer', 'min:1', 'max:10'],
            'instrument_lifetime_seconds' => ['required', 'integer', 'min:60', 'max:604800'],
            'max_hops' => ['required', 'integer', 'min:1', 'max:100'],
            'max_transfers_per_journal' => ['required', 'integer', 'min:1', 'max:100'],
            'max_cumulative_spending_minor' => ['required', 'integer', 'min:1', 'max:5000000000'],
            'minimum_protocol_version' => ['required', 'integer', 'in:1'],
            'max_device_exposure_minor' => ['required', 'integer', 'min:1', 'max:5000000000'],
            'max_incoming_exposure_per_user_minor' => ['required', 'integer', 'min:1', 'max:5000000000'],
            'max_transactions_per_device' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_journal_bytes' => ['required', 'integer', 'min:1024', 'max:1048576'],
            'supported_assets' => ['required', 'array', 'size:1'], 'supported_assets.0' => ['required', 'in:SIMUSDT'],
            'allowed_device_classes' => ['required', 'array', 'min:1', 'max:2'],
            'allowed_device_classes.*' => ['required', 'distinct', 'in:SIMULATOR_ONLY,SOFTWARE_ONLY'],
            'trust_epoch' => ['required', 'integer', 'min:1'], 'require_enrolled_device' => ['required', 'accepted'],
        ]);
        if ($payload['enabled'] && (!(bool) config('exapay_offline.enabled') || !\App\Services\ExaPay\Offline\OfflineEnvironmentGuard::allowsSimulation() || !$crypto->issuerPublicKey())) {
            return response()->json(['error_code' => 'offline_simulation_prerequisites_missing', 'message' => 'Simulation can only be enabled in an explicitly configured staging or test environment.'], 409);
        }
        $canonical = $this->canonicalChange($payload);
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        abort_unless(preg_match('/^[A-Za-z0-9._:-]{16,160}$/', $idempotencyKey) === 1, 422, 'A valid Idempotency-Key header is required.');
        $existing = ExaPayOfflineAdminChangeApproval::query()->where('requested_by_admin_id', $admin->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            abort_unless(hash_equals((string) $existing->change_digest, hash('sha256', $canonical)), 409, 'The idempotency key is already bound to another change.');
            return response()->json(['data' => ['approval_uuid' => $existing->approval_uuid, 'status' => $existing->status, 'expires_at' => $existing->expires_at->toIso8601String()]], 202);
        }
        $approval = ExaPayOfflineAdminChangeApproval::query()->create([
            'approval_uuid' => (string) Str::uuid(), 'change_type' => 'risk_policy', 'proposed_change' => $payload,
            'change_digest' => hash('sha256', $canonical), 'idempotency_key' => $idempotencyKey, 'requested_by_admin_id' => $admin->id,
            'status' => 'PENDING', 'expires_at' => now()->addMinutes(15),
        ]);
        $audit->log($admin, 'exapay.offline.risk_policy.change_proposed', [
            'approval_uuid' => $approval->approval_uuid, 'change_digest' => $approval->change_digest,
            'expires_at' => $approval->expires_at->toIso8601String(), 'requires_distinct_admin' => true,
        ], $request);

        return response()->json(['data' => ['approval_uuid' => $approval->approval_uuid, 'status' => 'PENDING_SECOND_ADMIN', 'expires_at' => $approval->expires_at->toIso8601String()]], 202);
    }

    public function approveRiskPolicy(Request $request, string $approvalUuid, AdminAuditService $audit, OfflineInstrumentCrypto $crypto, OfflineDeviceTrustService $trust): JsonResponse
    {
        $this->assertNonProduction();
        $admin = $this->admin($request);
        $approval = DB::transaction(function () use ($approvalUuid, $admin, $audit, $crypto, $trust, $request): ExaPayOfflineAdminChangeApproval {
            $approval = ExaPayOfflineAdminChangeApproval::query()->where('approval_uuid', $approvalUuid)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($approval->change_type, ['risk_policy', 'emergency_resume'], true) && $approval->status === 'PENDING' && $approval->expires_at->isFuture(), 409);
            abort_if((int) $approval->requested_by_admin_id === (int) $admin->id, 403, 'A different administrator must approve this policy change.');
            abort_unless(hash_equals((string) $approval->change_digest, hash('sha256', $this->canonicalChange((array) $approval->proposed_change))), 409);
            $payload = (array) $approval->proposed_change;
            if ($approval->change_type === 'emergency_resume') {
                abort_unless((bool) config('exapay_offline.enabled') && OfflineEnvironmentGuard::allowsSimulation() && $crypto->issuerPublicKey(), 409, 'Simulation prerequisites are not satisfied.');
                $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                abort_unless((int) $control->trust_epoch === (int) $payload['trust_epoch'], 409, 'Security controls changed after this resume proposal.');
                $policy = ExaPayOfflineRiskPolicy::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                abort_unless($policy->enabled, 409, 'A disabled policy must be reviewed and approved before resuming.');
                $policy->trust_epoch = max((int) $policy->trust_epoch, (int) $control->trust_epoch);
                $policy->policy_version = (int) $policy->policy_version + 1;
                $policy->policy_expires_at = now()->addHours(24);
                $canonicalPolicy = $this->policyContract($policy)->canonicalPayload();
                $signed = $crypto->signIssuerPayload(json_decode($canonicalPolicy, true, 512, JSON_THROW_ON_ERROR));
                $policy->policy_digest = hash('sha256', $canonicalPolicy); $policy->policy_signature = $signed['signature'];
                $policy->policy_public_key = $signed['issuer_public_key']; $policy->updated_by_admin_id = $admin->id; $policy->save();
                $trust->recordPolicyVersion($policy, $canonicalPolicy, $signed['signature'], $signed['issuer_public_key'], (int) $admin->id);
                $control->issuance_suspended = false; $control->redemption_suspended = false;
                $control->suspension_reason = null; $control->updated_by_admin_id = $admin->id; $control->save();
                $approval->status = 'APPROVED'; $approval->approved_by_admin_id = $admin->id; $approval->approved_at = now(); $approval->save();
                $audit->log($admin, 'exapay.offline.emergency_resumed', [
                    'approval_uuid' => $approval->approval_uuid, 'requested_by_admin_id' => $approval->requested_by_admin_id,
                    'trust_epoch' => $control->trust_epoch, 'policy_version' => $policy->policy_version,
                    'old_devices_remain_stale' => true, 'liabilities_released' => false,
                ], $request);
                return $approval->fresh();
            }
            if ($payload['enabled'] && (!(bool) config('exapay_offline.enabled') || !OfflineEnvironmentGuard::allowsSimulation() || !$crypto->issuerPublicKey())) {
                abort(409, 'Simulation prerequisites are not satisfied.');
            }
            $policy = ExaPayOfflineRiskPolicy::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $requiredEpoch = max((int) $policy->trust_epoch, (int) ExaPayOfflineSecurityControl::query()->whereKey(1)->value('trust_epoch'));
            abort_unless((int) $payload['trust_epoch'] >= $requiredEpoch, 409, 'Risk policy trust epoch cannot move backwards.');
            $before = $policy->only(array_keys($payload));
            $policy->fill($payload);
            $policy->updated_by_admin_id = $admin->id;
            $policy->policy_version = (int) $policy->policy_version + 1;
            $policy->policy_expires_at = now()->addHours(24);
            if ($policy->enabled) {
                $canonicalPolicy = $this->policyContract($policy)->canonicalPayload();
                $signed = $crypto->signIssuerPayload(json_decode($canonicalPolicy, true, 512, JSON_THROW_ON_ERROR));
                $policy->policy_digest = hash('sha256', $canonicalPolicy);
                $policy->policy_signature = $signed['signature'];
                $policy->policy_public_key = $signed['issuer_public_key'];
                $policy->save();
                $trust->recordPolicyVersion($policy, $canonicalPolicy, $signed['signature'], $signed['issuer_public_key'], (int) $admin->id);
            } else {
                $policy->policy_digest = null; $policy->policy_signature = null; $policy->policy_public_key = null;
                $policy->save();
            }
            $approval->status = 'APPROVED'; $approval->approved_by_admin_id = $admin->id; $approval->approved_at = now(); $approval->save();
            $audit->log($admin, 'exapay.offline.risk_policy.change_approved', [
                'approval_uuid' => $approval->approval_uuid, 'requested_by_admin_id' => $approval->requested_by_admin_id,
                'change_digest' => $approval->change_digest, 'policy_version' => $policy->policy_version,
                'before' => $before, 'after' => $policy->only(array_keys($payload)),
            ], $request);
            return $approval->fresh();
        }, 3);
        return response()->json(['data' => ['approval_uuid' => $approval->approval_uuid, 'status' => $approval->status, 'approved_at' => $approval->approved_at?->toIso8601String()]]);
    }

    public function suspend(Request $request, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:160'], 'suspend_redemption' => ['sometimes', 'boolean']]);
        $admin = $this->admin($request);
        $control = DB::transaction(function () use ($payload, $admin): ExaPayOfflineSecurityControl {
            $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $control->issuance_suspended = true;
            $control->redemption_suspended = (bool) ($payload['suspend_redemption'] ?? true);
            $control->trust_epoch = (int) $control->trust_epoch + 1;
            $control->suspension_reason = $payload['reason']; $control->updated_by_admin_id = $admin->id; $control->save();
            return $control->fresh();
        }, 3);
        $audit->log($admin, 'exapay.offline.emergency_suspension', ['issuance_suspended' => true, 'redemption_suspended' => $control->redemption_suspended, 'trust_epoch' => $control->trust_epoch, 'reason' => $payload['reason'], 'liabilities_released' => false], $request);
        return response()->json(['data' => ['issuance_suspended' => true, 'redemption_suspended' => $control->redemption_suspended, 'trust_epoch' => $control->trust_epoch, 'real_funds_enabled' => false]]);
    }

    public function proposeEmergencyResume(Request $request, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['reason' => ['required', 'string', 'min:12', 'max:240']]);
        $admin = $this->admin($request);
        $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->firstOrFail();
        abort_unless($control->issuance_suspended || $control->redemption_suspended, 409, 'There is no emergency suspension to resume.');
        $change = ['reason' => $payload['reason'], 'trust_epoch' => (int) $control->trust_epoch];
        $canonical = $this->canonicalChange($change);
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        abort_unless(preg_match('/^[A-Za-z0-9._:-]{16,160}$/', $idempotencyKey) === 1, 422, 'A valid Idempotency-Key header is required.');
        $existing = ExaPayOfflineAdminChangeApproval::query()->where('requested_by_admin_id', $admin->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            abort_unless(hash_equals((string) $existing->change_digest, hash('sha256', $canonical)), 409, 'The idempotency key is already bound to another change.');
            return response()->json(['data' => ['approval_uuid' => $existing->approval_uuid, 'status' => $existing->status]], 202);
        }
        $approval = ExaPayOfflineAdminChangeApproval::query()->create([
            'approval_uuid' => (string) Str::uuid(), 'change_type' => 'emergency_resume', 'proposed_change' => $change,
            'change_digest' => hash('sha256', $canonical), 'idempotency_key' => $idempotencyKey,
            'requested_by_admin_id' => $admin->id, 'status' => 'PENDING', 'expires_at' => now()->addMinutes(15),
        ]);
        $audit->log($admin, 'exapay.offline.emergency_resume.proposed', [
            'approval_uuid' => $approval->approval_uuid, 'trust_epoch' => (int) $control->trust_epoch,
            'requires_distinct_admin' => true, 'reason' => $payload['reason'],
        ], $request);
        return response()->json(['data' => ['approval_uuid' => $approval->approval_uuid, 'status' => 'PENDING_SECOND_ADMIN', 'expires_at' => $approval->expires_at->toIso8601String()]], 202);
    }

    public function policyApprovals(Request $request): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['status' => ['nullable', 'in:PENDING,APPROVED,REJECTED,EXPIRED'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = ExaPayOfflineAdminChangeApproval::query()->latest('id');
        if (isset($payload['status'])) $query->where('status', $payload['status']);
        $approvals = $query->paginate((int) ($payload['per_page'] ?? 50));
        $approvals->getCollection()->transform(static fn (ExaPayOfflineAdminChangeApproval $approval): array => [
            'approval_uuid' => $approval->approval_uuid, 'change_type' => $approval->change_type,
            'proposed_change' => $approval->proposed_change, 'change_digest' => $approval->change_digest,
            'requested_by_admin_id' => $approval->requested_by_admin_id, 'approved_by_admin_id' => $approval->approved_by_admin_id,
            'status' => $approval->status, 'expires_at' => $approval->expires_at?->toIso8601String(),
            'approved_at' => $approval->approved_at?->toIso8601String(),
        ]);
        return response()->json(['data' => $approvals]);
    }

    public function restrictDeviceFamily(Request $request, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['family' => ['required', 'in:android_keystore,strongbox,javacard,secure_element'], 'reason' => ['required', 'string', 'min:8', 'max:160']]);
        $admin = $this->admin($request);
        $control = DB::transaction(function () use ($payload, $admin): ExaPayOfflineSecurityControl {
            $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $families = array_values(array_unique(array_merge((array) $control->blocked_device_families, [$payload['family']])));
            $control->blocked_device_families = $families;
            $control->suspension_reason = $payload['reason']; $control->updated_by_admin_id = $admin->id; $control->save();
            return $control->fresh();
        }, 3);
        $audit->log($admin, 'exapay.offline.device_family.restricted', ['family' => $payload['family'], 'trust_epoch' => $control->trust_epoch, 'reason' => $payload['reason'], 'liabilities_released' => false], $request);
        return response()->json(['data' => ['blocked_device_families' => $control->blocked_device_families, 'trust_epoch' => $control->trust_epoch]]);
    }

    public function exposure(OfflineExposureService $exposure): JsonResponse
    {
        $this->assertNonProduction();
        return response()->json(['data' => $exposure->snapshot()]);
    }

    public function issuerKeys(): JsonResponse
    {
        $this->assertNonProduction();
        return response()->json(['data' => ExaPayOfflineIssuerKey::query()->latest('id')->get(['key_id', 'fingerprint', 'status', 'activated_at', 'compromised_at', 'compromise_reason'])]);
    }

    public function compromiseDevice(Request $request, int $device, OfflineStoredValueService $service, OfflineDeviceTrustService $trust, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:160']]);
        $admin = $this->admin($request);
        $model = $service->suspendDevice($device, $payload['reason']);
        $model->trust_class = 'COMPROMISED'; $model->attestation_status = 'REJECTED'; $model->trust_epoch = max((int) $model->trust_epoch, (int) ExaPayOfflineSecurityControl::query()->whereKey(1)->value('trust_epoch'));
        $model->save();
        $trust->recordFraud('DEVICE_MARKED_COMPROMISED', 'CRITICAL', (int) $model->user_id, (int) $model->id, ['reason' => $payload['reason'], 'outstanding_liability_released' => false]);
        $audit->log($admin, 'exapay.offline.device.compromised', ['device_uuid' => $model->device_uuid, 'reason' => $payload['reason'], 'outstanding_liability_released' => false], $request);
        return response()->json(['data' => ['device_uuid' => $model->device_uuid, 'trust_class' => $model->trust_class, 'status' => $model->status]]);
    }

    public function investigateFraudCase(Request $request, int $caseId, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['status' => ['required', 'in:INVESTIGATING,RESOLVED'], 'resolution_code' => ['required_if:status,RESOLVED', 'nullable', 'string', 'max:64']]);
        $admin = $this->admin($request);
        $case = DB::transaction(function () use ($caseId, $payload, $admin): ExaPayOfflineFraudCase {
            $case = ExaPayOfflineFraudCase::query()->whereKey($caseId)->lockForUpdate()->firstOrFail();
            abort_unless($case->status !== 'RESOLVED', 409);
            $case->status = $payload['status']; $case->investigated_by_admin_id = $admin->id; $case->investigated_at = now();
            $case->resolution_code = $payload['resolution_code'] ?? null; $case->save();
            return $case->fresh();
        }, 3);
        $audit->log($admin, 'exapay.offline.fraud_case.updated', ['case_uuid' => $case->case_uuid, 'status' => $case->status, 'resolution_code' => $case->resolution_code], $request);
        return response()->json(['data' => $case]);
    }

    public function fraudCases(Request $request): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['status' => ['nullable', 'in:OPEN,INVESTIGATING,RESOLVED'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = ExaPayOfflineFraudCase::query()->latest('id');
        if (isset($payload['status'])) $query->where('status', $payload['status']);
        return response()->json(['data' => $query->paginate((int) ($payload['per_page'] ?? 50))]);
    }

    public function compromiseIssuerKey(Request $request, string $keyId, AdminAuditService $audit): JsonResponse
    {
        $this->assertNonProduction();
        $payload = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:160']]);
        $admin = $this->admin($request);
        DB::transaction(function () use ($keyId, $payload, $admin): void {
            $key = ExaPayOfflineIssuerKey::query()->where('key_id', $keyId)->lockForUpdate()->firstOrFail();
            $key->status = 'COMPROMISED'; $key->compromised_at = now(); $key->compromise_reason = $payload['reason']; $key->recorded_by_admin_id = $admin->id; $key->save();
            $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $control->issuance_suspended = true; $control->redemption_suspended = true;
            $control->trust_epoch = (int) $control->trust_epoch + 1; $control->suspension_reason = 'issuer_key_compromise';
            $control->updated_by_admin_id = $admin->id; $control->save();
        }, 3);
        $audit->log($admin, 'exapay.offline.issuer_key.compromised', ['key_id' => $keyId, 'reason' => $payload['reason'], 'issuance_suspended' => true, 'redemption_suspended' => true, 'liabilities_released' => false], $request);
        return response()->json(['data' => ['key_id' => $keyId, 'status' => 'COMPROMISED', 'issuance_suspended' => true, 'redemption_suspended' => true]]);
    }

    public function auditEvents(): JsonResponse
    {
        $this->assertNonProduction();

        return response()->json(['data' => AuditLog::query()->where('action', 'like', 'exapay.offline.%')->latest('created_at')->paginate(100)]);
    }

    private function assertNonProduction(): void
    {
        abort_unless(\App\Services\ExaPay\Offline\OfflineEnvironmentGuard::allowsSimulation(), 404);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }

    private function policyContract(ExaPayOfflineRiskPolicy $policy): OfflineRiskPolicyContract
    {
        return new OfflineRiskPolicyContract(
            (int) $policy->policy_version, (bool) $policy->enabled, (int) $policy->max_amount_minor,
            (int) $policy->max_outstanding_per_user_minor, (int) $policy->max_devices_per_user,
            (int) $policy->instrument_lifetime_seconds, (int) $policy->max_hops,
            (int) $policy->max_transfers_per_journal, (int) $policy->max_cumulative_spending_minor,
            (int) $policy->minimum_protocol_version, $policy->policy_expires_at?->timestamp ?? 0,
            (int) $policy->max_device_exposure_minor, (int) $policy->max_incoming_exposure_per_user_minor,
            (int) $policy->max_transactions_per_device, (int) $policy->max_journal_bytes,
            (array) $policy->supported_assets, (array) $policy->allowed_device_classes,
            (int) $policy->trust_epoch, (bool) $policy->require_enrolled_device,
        );
    }

    private function canonicalChange(array $payload): string
    {
        $normalize = function (array $value) use (&$normalize): array {
            ksort($value);
            foreach ($value as $key => $item) if (is_array($item)) $value[$key] = $normalize($item);
            return $value;
        };
        return json_encode($normalize($payload), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
