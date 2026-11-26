<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflinePaymentException;
use App\Models\ExaPayOfflineDevice;
use App\Models\ExaPayOfflineFraudCase;
use App\Models\ExaPayOfflineIssuerKey;
use App\Models\ExaPayOfflinePolicyVersion;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Models\ExaPayOfflineSecurityControl;
use App\Services\AuditLogService;
use Illuminate\Support\Str;

final class OfflineDeviceTrustService
{
    public function __construct(private readonly AuditLogService $audit)
    {
    }

    public function assertIssuanceAllowed(ExaPayOfflineDevice $device, ExaPayOfflineRiskPolicy $policy): void
    {
        $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->first();
        if (!$control || $control->issuance_suspended) {
            throw new OfflinePaymentException('offline_issuance_suspended', 'Offline issuance is suspended by the issuer.', 503);
        }
        if ($device->status !== 'active') {
            throw new OfflinePaymentException('device_suspended', 'This device is suspended from offline payment use.', 403);
        }
        $this->assertEpochAndClass($device, $policy, $control);
    }

    public function assertTransferAllowed(ExaPayOfflineDevice $sender, ExaPayOfflineDevice $receiver, $commitment, ExaPayOfflineRiskPolicy $policy): void
    {
        $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->first();
        if (!$control) throw new OfflinePaymentException('offline_security_controls_unavailable', 'Offline issuer controls are unavailable.', 503);
        foreach ([$sender, $receiver] as $device) {
            try {
                $this->assertEpochAndClass($device, $policy, $control);
            } catch (OfflinePaymentException $exception) {
                $this->recordFraud(
                    $exception->errorCode === 'device_trust_epoch_stale' ? 'STALE_DEVICE_TRUST_EPOCH' : 'DEVICE_TRUST_POLICY_VIOLATION',
                    'HIGH', (int) $device->user_id, (int) $device->id,
                    ['transfer_uuid' => $commitment->transferUuid, 'device_uuid' => $device->device_uuid, 'device_trust_epoch' => (int) $device->trust_epoch, 'current_trust_epoch' => max((int) $policy->trust_epoch, (int) $control->trust_epoch)],
                );
                throw $exception;
            }
        }
    }

    public function assertRedemptionAllowed(): void
    {
        $control = ExaPayOfflineSecurityControl::query()->whereKey(1)->first();
        if (!$control || $control->redemption_suspended) {
            throw new OfflinePaymentException('offline_redemption_suspended', 'Offline redemption is suspended by the issuer.', 503);
        }
    }

    public function latestPolicyVersion(): int
    {
        return (int) (ExaPayOfflinePolicyVersion::query()->max('policy_version') ?? 0);
    }

    public function recordPolicyVersion(ExaPayOfflineRiskPolicy $policy, string $canonical, string $signature, string $publicKey, ?int $adminId = null): ExaPayOfflinePolicyVersion
    {
        $digest = hash('sha256', $canonical);
        $keyId = substr(hash('sha256', $publicKey), 0, 32);
        $key = ExaPayOfflineIssuerKey::query()->where('key_id', $keyId)->first();
        if ($key?->status === 'COMPROMISED') {
            throw new OfflinePaymentException('issuer_key_compromised', 'A compromised issuer key cannot sign a new policy.', 503);
        }
        ExaPayOfflineIssuerKey::query()->where('status', 'ACTIVE')->where('key_id', '!=', $keyId)->update(['status' => 'RETIRED', 'updated_at' => now()]);
        ExaPayOfflineIssuerKey::query()->firstOrCreate(['key_id' => $keyId], [
            'public_key' => $publicKey, 'fingerprint' => hash('sha256', preg_replace('/\s+/', '', $publicKey) ?: $publicKey),
            'status' => 'ACTIVE', 'activated_at' => now(), 'recorded_by_admin_id' => $adminId,
        ]);
        return ExaPayOfflinePolicyVersion::query()->create([
            'policy_version' => (int) $policy->policy_version, 'policy_digest' => $digest,
            'canonical_payload' => $canonical, 'policy_signature' => $signature,
            'issuer_public_key' => $publicKey, 'issuer_key_id' => $keyId,
            'status' => 'ACTIVE', 'created_by_admin_id' => $adminId,
            'expires_at' => $policy->policy_expires_at, 'created_at' => now(),
        ]);
    }

    public function recordFraud(string $reasonCode, string $severity, ?int $userId, ?int $deviceId, array $evidence): ExaPayOfflineFraudCase
    {
        $case = ExaPayOfflineFraudCase::query()->create([
            'case_uuid' => (string) Str::uuid(), 'user_id' => $userId, 'device_id' => $deviceId,
            'reason_code' => $reasonCode, 'severity' => $severity, 'status' => 'OPEN',
            'evidence' => $evidence + ['simulation_only' => true, 'asset' => 'SIMUSDT'], 'detected_at' => now(),
        ]);
        $this->audit->log($userId, 'exapay.offline.fraud.detected', request(), [
            'case_uuid' => $case->case_uuid, 'reason_code' => $reasonCode, 'severity' => $severity,
            'device_id' => $deviceId, 'simulation_only' => true,
        ]);

        return $case;
    }

    private function assertEpochAndClass(ExaPayOfflineDevice $device, ExaPayOfflineRiskPolicy $policy, ExaPayOfflineSecurityControl $control): void
    {
        $requiredEpoch = max((int) $policy->trust_epoch, (int) $control->trust_epoch);
        if ((int) $device->trust_epoch < $requiredEpoch) {
            throw new OfflinePaymentException('device_trust_epoch_stale', 'The device trust epoch is stale and requires authenticated reconnection.', 409);
        }
        if (!in_array((string) $device->trust_class, (array) $policy->allowed_device_classes, true)
            || $device->attestation_status === 'REJECTED'
            || ($device->trust_class === 'VERIFIED_HARDWARE' && $device->attestation_status !== 'VERIFIED')) {
            throw new OfflinePaymentException('device_trust_class_not_allowed', 'The device does not satisfy the signed trust policy.', 403);
        }
        $family = (string) ($device->attestation_format ?? 'software');
        if ($device->attestation_status === 'VERIFIED' && in_array($family, (array) $control->blocked_device_families, true)) {
            throw new OfflinePaymentException('device_family_restricted', 'This device family is blocked by issuer security controls.', 403);
        }
    }
}
