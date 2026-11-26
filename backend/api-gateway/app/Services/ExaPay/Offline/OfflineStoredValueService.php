<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflineInstrumentState;
use App\Domain\ExaPay\Offline\OfflineIssuanceContract;
use App\Domain\ExaPay\Offline\OfflineRiskPolicyContract;
use App\Domain\ExaPay\Offline\OfflinePaymentException;
use App\Models\Account;
use App\Models\ExaPayOfflineDevice;
use App\Models\ExaPayOfflineDeviceKeyHistory;
use App\Models\ExaPayOfflineInstrument;
use App\Models\ExaPayOfflineReserve;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OfflineStoredValueService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly OfflineInstrumentCrypto $crypto,
        private readonly AuditLogService $audit,
    ) {
    }

    public function capabilities(): array
    {
        $enabled = $this->isEnabled();
        $policy = ExaPayOfflineRiskPolicy::query()->first();
        $securityControl = \App\Models\ExaPayOfflineSecurityControl::query()->whereKey(1)->first();
        $clientPolicy = $this->signedPolicyForClient($policy);
        $policyReady = $enabled && (bool) ($policy?->enabled ?? false) && $clientPolicy !== null && $securityControl !== null;

        return [
            'payment_modes' => ['ONLINE_INTERNAL_TRANSFER', 'OFFLINE_STORED_VALUE'],
            'offline_simulation_enabled' => $enabled && (bool) ($policy?->enabled ?? false) && $this->crypto->issuerPublicKey() !== null && !($securityControl?->issuance_suspended ?? true),
            'asset' => 'SIMUSDT',
            'minor_units_per_token' => 100,
            'protocol_version' => (int) config('exapay_offline.protocol_version', 1),
            'signature_algorithm' => 'SHA256 with RSA-2048+ or P-256+ keys',
            'issuer_public_key' => $enabled ? $this->crypto->issuerPublicKey() : null,
            'risk_policy' => $clientPolicy,
            'hardware_security_level' => 'software_key; platform secure-element binding is not attested',
            'offline_finality' => false,
            'redemption_enabled' => $policyReady && (bool) config('exapay_offline.redemption_enabled') && OfflineEnvironmentGuard::allowsSimulation() && !($securityControl?->redemption_suspended ?? true),
            'multi_hop_enabled' => false,
            'multi_hop_reconciliation_supported' => $policyReady,
            'real_funds_accessible' => false,
        ];
    }

    public function enrollDevice(User $user, array $input): ExaPayOfflineDevice
    {
        $this->assertEnabled();
        $deviceUuid = strtolower((string) $input['device_uuid']);
        $publicKey = trim((string) $input['public_key']);
        $nonce = (string) $input['nonce'];
        $signedAt = (int) $input['signed_at'];
        $this->assertFreshProof($nonce, $signedAt);
        $fingerprint = hash('sha256', preg_replace('/\s+/', '', $publicKey) ?: $publicKey);
        $message = $this->enrollmentMessage((int) $user->id, $deviceUuid, $nonce, $signedAt);

        if (!$this->crypto->verifyDeviceProof($publicKey, $message, (string) $input['signature'])) {
            app(OfflineDeviceTrustService::class)->recordFraud('INVALID_DEVICE_ENROLLMENT_PROOF', 'HIGH', (int) $user->id, null, [
                'device_uuid' => $deviceUuid, 'presented_key_fingerprint' => $fingerprint,
            ]);
            throw new OfflinePaymentException('device_proof_invalid', 'Device ownership could not be verified.', 403);
        }
        $identityConflict = ExaPayOfflineDevice::query()->where('device_uuid', $deviceUuid)->where('public_key_fingerprint', '!=', $fingerprint)->first();
        if ($identityConflict) {
            app(OfflineDeviceTrustService::class)->recordFraud('CLONED_DEVICE_IDENTITY', 'CRITICAL', (int) $identityConflict->user_id, (int) $identityConflict->id, [
                'device_uuid' => $deviceUuid, 'known_fingerprint' => $identityConflict->public_key_fingerprint,
                'presented_fingerprint' => $fingerprint,
            ]);
            throw new OfflinePaymentException('device_key_conflict', 'This device identity is already enrolled with a different key.', 409);
        }

        return DB::transaction(function () use ($user, $deviceUuid, $publicKey, $fingerprint, $nonce, $signedAt): ExaPayOfflineDevice {
            // Serialize device-count and identity checks for this account on PostgreSQL.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $policy = $this->policy();
            $existing = ExaPayOfflineDevice::query()->where('user_id', $user->id)->where('device_uuid', $deviceUuid)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->public_key_fingerprint !== $fingerprint) {
                    throw new OfflinePaymentException('device_key_conflict', 'This device identity is already enrolled with a different key.', 409);
                }
                if ($existing->status !== 'active') {
                    throw new OfflinePaymentException('device_suspended', 'This device is suspended. Contact support before using offline payments.', 403);
                }
                $this->consumeNonce((int) $user->id, $deviceUuid, $nonce, 'enrollment', $signedAt);
                $existing->last_seen_at = now();
                $existing->save();

                return $existing;
            }

            $deviceCount = ExaPayOfflineDevice::query()->where('user_id', $user->id)->count();
            if ($deviceCount >= (int) $policy->max_devices_per_user) {
                throw new OfflinePaymentException('device_limit_reached', 'The maximum number of enrolled devices has been reached.', 422);
            }
            $this->consumeNonce((int) $user->id, $deviceUuid, $nonce, 'enrollment', $signedAt);
            $device = ExaPayOfflineDevice::query()->create([
                'user_id' => $user->id,
                'device_uuid' => $deviceUuid,
                'public_key' => $publicKey,
                'public_key_fingerprint' => $fingerprint,
                'status' => 'active',
                'protocol_version' => (int) config('exapay_offline.protocol_version', 1),
                'trust_class' => 'SOFTWARE_ONLY', 'attestation_status' => 'NOT_VERIFIED',
                'trust_epoch' => (int) $policy->trust_epoch,
                'offline_spending_allowance_minor' => (int) $policy->max_device_exposure_minor,
                'device_counter' => 0,
                'journal_counter' => 0,
                'enrolled_at' => now(),
                'last_seen_at' => now(),
            ]);
            ExaPayOfflineDeviceKeyHistory::query()->create([
                'device_id' => $device->id, 'key_version' => 1, 'public_key' => $publicKey,
                'public_key_fingerprint' => $fingerprint, 'status' => 'active', 'valid_from' => now(),
            ]);
            $this->audit->log((int) $user->id, 'exapay.offline.device.enrolled', request(), [
                'device_id' => $device->id, 'device_uuid' => $deviceUuid, 'key_fingerprint' => $fingerprint,
                'protocol_version' => $device->protocol_version, 'simulation_only' => true,
            ]);

            return $device;
        }, 3);
    }

    public function recordAttestation(User $user, string $deviceUuid, string $format, array $attestation, OfflineDeviceAttestationVerifier $verifier): array
    {
        $this->assertEnabled();
        return DB::transaction(function () use ($user, $deviceUuid, $format, $attestation, $verifier): array {
            $device = ExaPayOfflineDevice::query()->where('user_id', $user->id)->where('device_uuid', strtolower($deviceUuid))->lockForUpdate()->firstOrFail();
            $assessment = $verifier->verify((string) $device->device_uuid, (string) $device->public_key_fingerprint, $attestation);
            $allowedStatuses = ['UNSUPPORTED', 'NOT_VERIFIED', 'VERIFIED', 'REJECTED'];
            $status = in_array($assessment['status'] ?? null, $allowedStatuses, true) ? (string) $assessment['status'] : 'NOT_VERIFIED';
            $trustedClass = ($status === 'VERIFIED' && ($assessment['trust_class'] ?? null) === 'VERIFIED_HARDWARE'
                && preg_match('/^[a-f0-9]{64}$/', (string) ($assessment['attestation_digest'] ?? '')) === 1)
                ? 'VERIFIED_HARDWARE' : 'SOFTWARE_ONLY';
            $device->attestation_status = $status;
            $device->attestation_format = $format;
            $device->attestation_digest = $status === 'VERIFIED' ? (string) ($assessment['attestation_digest'] ?? '') : null;
            $device->trust_class = $trustedClass;
            $device->last_seen_at = now();
            $device->save();
            app(AuditLogService::class)->log((int) $user->id, 'exapay.offline.device.attestation_assessed', request(), [
                'device_uuid' => $device->device_uuid, 'attestation_format' => $format, 'attestation_status' => $status,
                'trust_class' => $trustedClass, 'reason_code' => $assessment['reason_code'] ?? null,
                'real_funds_accessible' => false,
            ]);
            return ['device_uuid' => $device->device_uuid, 'attestation_status' => $status, 'trust_class' => $trustedClass,
                'reason_code' => $assessment['reason_code'] ?? null, 'real_funds_accessible' => false];
        }, 3);
    }

    public function balanceOverview(User $user): array
    {
        $this->assertEnabled();
        $online = Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'SIMUSDT')->value('balance') ?? '0';
        $outstandingMinor = (int) \App\Models\ExaPayOfflineValueOutput::query()
            ->where('owner_user_id', $user->id)->where('asset', 'SIMUSDT')->whereIn('status', ['available', 'locked', 'quarantined'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('exapay_offline_output_spends as s')
                    ->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid');
            })->sum('amount_minor');

        return [
            'asset' => 'SIMUSDT',
            'minor_units_per_token' => 100,
            'online_available_minor' => $this->decimalToMinor((string) $online),
            'offline_outstanding_minor' => $outstandingMinor,
            'offline_outstanding' => $this->minorToDecimal($outstandingMinor),
            'accounting' => $this->accountingSnapshot(),
            'real_funds_accessible' => false,
        ];
    }

    public function issue(User $user, OfflineIssuanceContract $request): array
    {
        $this->assertEnabled();
        $requestHash = hash('sha256', json_encode([
            'mode' => $request->mode->value, 'user_id' => (int) $user->id, 'device_uuid' => strtolower($request->deviceUuid),
            'asset' => $request->asset, 'amount_minor' => $request->amountMinor,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $request, $requestHash): array {
            // Serialize per-user idempotency/outstanding checks, even across two enrolled devices.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $policy = $this->policy(true);
            if (!$policy->enabled) {
                throw new OfflinePaymentException('offline_simulation_disabled', 'Offline simulation is currently disabled.', 403);
            }
            $this->assertSignedPolicy($policy);
            $device = ExaPayOfflineDevice::query()
                ->where('user_id', $user->id)->where('device_uuid', strtolower($request->deviceUuid))->lockForUpdate()->first();
            if (!$device) {
                throw new OfflinePaymentException('device_not_enrolled', 'Enroll this device before requesting an offline instrument.', 403);
            }
            app(OfflineDeviceTrustService::class)->assertIssuanceAllowed($device, $policy);
            if ($request->amountMinor > (int) $policy->max_amount_minor) {
                throw new OfflinePaymentException('amount_limit_exceeded', 'The requested amount exceeds the current offline simulation limit.', 422);
            }

            if (!$device) {
                throw new OfflinePaymentException('device_not_enrolled', 'Enroll this device before requesting an offline instrument.', 403);
            }
            if ($device->status !== 'active') {
                throw new OfflinePaymentException('device_suspended', 'This device is suspended from offline payment use.', 403);
            }
            $deviceExposure = (int) \App\Models\ExaPayOfflineValueOutput::query()->where('owner_device_id', $device->id)->where('asset', 'SIMUSDT')
                ->whereIn('status', ['available', 'locked', 'quarantined'])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('exapay_offline_output_spends as s')
                        ->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid');
                })->sum('amount_minor');
            if ($request->amountMinor > (int) $policy->max_device_exposure_minor - $deviceExposure) {
                throw new OfflinePaymentException('device_exposure_limit_exceeded', 'Issuance would exceed the signed device offline exposure allowance.', 422);
            }

            $existing = ExaPayOfflineInstrument::query()->where('user_id', $user->id)->where('idempotency_key', $request->idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals((string) $existing->request_hash, $requestHash)) {
                    throw new OfflinePaymentException('idempotency_conflict', 'This idempotency key was already used for a different issuance request.', 409);
                }

                return $this->instrumentResponse($existing) + ['idempotent_replay' => true];
            }

            $funding = Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            if (!$funding || FinancialDecimal::compare((string) $funding->balance, $this->minorToDecimal($request->amountMinor)) < 0) {
                throw new OfflinePaymentException('insufficient_simulated_balance', 'There is not enough SIMUSDT in the online simulation balance.', 422);
            }
            if (FinancialDecimal::compare((string) $funding->balance, $this->minorToDecimal($this->decimalToMinor((string) $funding->balance))) !== 0) {
                throw new OfflinePaymentException('simulated_balance_precision_conflict', 'The SIMUSDT balance requires reconciliation before offline issuance.', 409);
            }

            $reserveAccount = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            if (!$reserveAccount || !$reserve
                || (int) $reserve->reserved_minor !== (int) $reserve->outstanding_liability_minor
                || (int) $reserve->issued_minor !== (int) $reserve->outstanding_liability_minor + (int) $reserve->redeemed_minor + (int) $reserve->extinguished_minor
                || FinancialDecimal::compare((string) $reserveAccount->balance, $this->minorToDecimal((int) $reserve->reserved_minor)) !== 0) {
                throw new OfflinePaymentException('reserve_unavailable', 'The simulation reserve is unavailable or requires reconciliation.', 503);
            }

            $userOutstanding = (int) \App\Models\ExaPayOfflineValueOutput::query()
                ->where('owner_user_id', $user->id)->where('asset', 'SIMUSDT')->whereIn('status', ['available', 'locked', 'quarantined'])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('exapay_offline_output_spends as s')
                        ->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid');
                })->sum('amount_minor');
            if ($request->amountMinor > (int) $policy->max_outstanding_per_user_minor - $userOutstanding) {
                throw new OfflinePaymentException('outstanding_limit_exceeded', 'This issuance would exceed the user offline liability limit.', 422);
            }
            if ($request->amountMinor > PHP_INT_MAX - (int) $reserve->reserved_minor
                || $request->amountMinor > PHP_INT_MAX - (int) $reserve->outstanding_liability_minor
                || $request->amountMinor > PHP_INT_MAX - (int) $reserve->issued_minor) {
                throw new OfflinePaymentException('reserve_limit_exceeded', 'The offline simulation reserve has reached its configured capacity.', 409);
            }

            $deviceCounter = (int) $device->device_counter + 1;
            $now = now();
            $reference = 'EXAO-' . Str::upper(Str::random(24));
            $instrumentUuid = (string) Str::uuid();
            $nonce = Str::random(48);
            $lifetime = min(604800, max(60, (int) $policy->instrument_lifetime_seconds));
            $issuerPublicKey = $this->crypto->issuerPublicKey();
            if (!$issuerPublicKey) {
                throw new OfflinePaymentException('issuer_unavailable', 'Offline instrument issuance is not configured.', 503);
            }
            $payload = [
                'protocol_version' => (int) config('exapay_offline.protocol_version', 1),
                'payment_mode' => 'OFFLINE_STORED_VALUE',
                'environment' => 'SIMULATION',
                'instrument_id' => $instrumentUuid,
                'reference' => $reference,
                'owner_user_id' => (int) $user->id,
                'device_uuid' => (string) $device->device_uuid,
                'device_key_version' => (int) $device->key_version,
                'device_trust_epoch' => (int) $device->trust_epoch,
                'asset' => $request->asset,
                'amount_minor' => $request->amountMinor,
                'minor_units_per_token' => 100,
                'device_counter' => $deviceCounter,
                'nonce' => $nonce,
                'issuer_key_id' => substr(hash('sha256', $issuerPublicKey), 0, 32),
                'issued_at' => $now->timestamp,
                'expires_at' => $now->copy()->addSeconds($lifetime)->timestamp,
            ];
            $signed = $this->crypto->signIssuerPayload($payload);

            $instrument = ExaPayOfflineInstrument::query()->create([
                'instrument_uuid' => $instrumentUuid,
                'reference' => $reference,
                'user_id' => $user->id,
                'device_id' => $device->id,
                'asset' => 'SIMUSDT',
                'amount_minor' => $request->amountMinor,
                'idempotency_key' => $request->idempotencyKey,
                'request_hash' => $requestHash,
                'device_counter' => $deviceCounter,
                'protocol_version' => $payload['protocol_version'],
                'nonce' => $nonce,
                'payload' => $payload,
                'issuer_signature' => $signed['signature'],
                'issuer_public_key' => $signed['issuer_public_key'],
                'status' => OfflineInstrumentState::PENDING_ACKNOWLEDGMENT->value,
                'delivery_status' => 'pending',
                'issued_at' => $now,
                'expires_at' => $now->copy()->addSeconds($lifetime),
            ]);

            \App\Models\ExaPayOfflineValueOutput::query()->create([
                'output_uuid' => $instrumentUuid, 'source_instrument_id' => $instrument->id,
                'owner_user_id' => $user->id, 'owner_device_id' => $device->id, 'asset' => 'SIMUSDT',
                'amount_minor' => $request->amountMinor, 'hop_count' => 0,
                'lineage_digest' => $this->rootLineageDigest($instrumentUuid, $payload), 'status' => 'available', 'version' => 0,
            ]);

            $referenceForLedger = 'EXAPAY-OFFLINE-' . strtoupper(str_replace('-', '', $instrumentUuid));
            $this->ledger->postDoubleEntry($referenceForLedger, 'SIMUSDT offline value issuance reserve', [
                ['account_id' => $funding->id, 'amount' => FinancialDecimal::sub('0', $this->minorToDecimal($request->amountMinor)), 'asset' => 'SIMUSDT', 'user_id' => (int) $user->id, 'metadata' => ['offline_instrument' => $instrumentUuid]],
                ['account_id' => $reserveAccount->id, 'amount' => $this->minorToDecimal($request->amountMinor), 'asset' => 'SIMUSDT', 'metadata' => ['offline_instrument' => $instrumentUuid]],
            ], 'exapay_offline_issue', [
                'source_service' => 'exapay_offline_simulation', 'initiated_by_type' => 'user', 'initiated_by_id' => (int) $user->id,
                'payment_mode' => 'OFFLINE_STORED_VALUE', 'instrument_reference' => $reference,
                'amount_minor' => $request->amountMinor, 'real_funds' => false,
            ]);

            $reserve->reserved_minor = (int) $reserve->reserved_minor + $request->amountMinor;
            $reserve->outstanding_liability_minor = (int) $reserve->outstanding_liability_minor + $request->amountMinor;
            $reserve->issued_minor = (int) $reserve->issued_minor + $request->amountMinor;
            $reserve->version = (int) $reserve->version + 1;
            $reserve->save();
            $device->device_counter = $deviceCounter;
            $device->last_seen_at = now();
            $device->save();
            $this->audit->log((int) $user->id, 'exapay.offline.instrument.issued', request(), [
                'instrument_id' => $instrumentUuid, 'reference' => $reference, 'device_id' => $device->id,
                'asset' => 'SIMUSDT', 'amount_minor' => $request->amountMinor, 'state' => $instrument->status,
                'ledger_reference' => $referenceForLedger, 'real_funds' => false,
            ]);

            return $this->instrumentResponse($instrument, $signed['issuer_public_key']) + ['idempotent_replay' => false];
        }, 3);
    }

    public function status(User $user, string $reference): array
    {
        $this->assertEnabled();
        $instrument = ExaPayOfflineInstrument::query()->where('user_id', $user->id)->where('reference', $reference)->first();
        if (!$instrument) {
            throw new OfflinePaymentException('instrument_not_found', 'Offline instrument was not found for this account.', 404);
        }
        $status = $instrument->expires_at->isPast() && !in_array($instrument->status, [OfflineInstrumentState::REDEEMED->value, OfflineInstrumentState::FAILED->value], true)
            ? OfflineInstrumentState::EXPIRED->value
            : $instrument->status;

        return [
            'reference' => $instrument->reference,
            'payment_mode' => 'OFFLINE_STORED_VALUE',
            'asset' => 'SIMUSDT',
            'amount_minor' => (int) $instrument->amount_minor,
            'status' => $status,
            'delivery_status' => $instrument->delivery_status,
            'issued_at' => $instrument->issued_at?->toIso8601String(),
            'expires_at' => $instrument->expires_at?->toIso8601String(),
            'acknowledged_at' => $instrument->acknowledged_at?->toIso8601String(),
            'liability_released' => false,
            'real_funds' => false,
        ];
    }

    public function acknowledge(User $user, string $reference, array $proof): array
    {
        $this->assertEnabled();

        return DB::transaction(function () use ($user, $reference, $proof): array {
            $device = $this->activeDevice($user, (string) $proof['device_uuid']);
            $instrument = ExaPayOfflineInstrument::query()->where('user_id', $user->id)->where('device_id', $device->id)->where('reference', $reference)->lockForUpdate()->first();
            if (!$instrument) {
                throw new OfflinePaymentException('instrument_not_found', 'Offline instrument was not found for this device.', 404);
            }
            if ($instrument->expires_at->isPast() && $instrument->delivery_status !== 'acknowledged') {
                throw new OfflinePaymentException('instrument_expired', 'This instrument expired before delivery acknowledgment.', 409);
            }
            if ($instrument->delivery_status === 'acknowledged') {
                return ['reference' => $reference, 'status' => $instrument->status, 'delivery_status' => 'acknowledged', 'acknowledged_at' => $instrument->acknowledged_at?->toIso8601String()];
            }
            $nonce = (string) $proof['nonce'];
            $signedAt = (int) $proof['signed_at'];
            $this->assertFreshProof($nonce, $signedAt);
            $message = implode('|', ['exaearn-offline-ack:v1', $reference, $device->device_uuid, $nonce, (string) $signedAt]);
            if (!$this->crypto->verifyDeviceProof((string) $device->public_key, $message, (string) $proof['signature'])) {
                throw new OfflinePaymentException('device_proof_invalid', 'Device acknowledgment could not be verified.', 403);
            }
            $this->consumeNonce((int) $user->id, (string) $device->device_uuid, $nonce, 'ack', $signedAt);
            $instrument->delivery_status = 'acknowledged';
            $instrument->status = OfflineInstrumentState::ACKNOWLEDGED->value;
            $instrument->acknowledged_at = now();
            $instrument->save();
            $device->last_seen_at = now();
            $device->save();
            $this->audit->log((int) $user->id, 'exapay.offline.instrument.acknowledged', request(), ['instrument_id' => $instrument->instrument_uuid, 'reference' => $reference, 'device_id' => $device->id]);

            return ['reference' => $reference, 'status' => $instrument->status, 'delivery_status' => $instrument->delivery_status, 'acknowledged_at' => $instrument->acknowledged_at?->toIso8601String()];
        }, 3);
    }

    public function recover(User $user, string $reference, array $proof): array
    {
        $this->assertEnabled();

        return DB::transaction(function () use ($user, $reference, $proof): array {
            $device = $this->activeDevice($user, (string) $proof['device_uuid']);
            $instrument = ExaPayOfflineInstrument::query()->where('user_id', $user->id)->where('device_id', $device->id)->where('reference', $reference)->lockForUpdate()->first();
            if (!$instrument) {
                throw new OfflinePaymentException('instrument_not_found', 'Offline instrument was not found for this device.', 404);
            }
            if ($instrument->expires_at->isPast() || in_array($instrument->status, [OfflineInstrumentState::SUSPENDED->value, OfflineInstrumentState::FAILED->value], true)) {
                throw new OfflinePaymentException('instrument_not_recoverable', 'This instrument is not available for recovery.', 409);
            }
            $nonce = (string) $proof['nonce'];
            $signedAt = (int) $proof['signed_at'];
            $this->assertFreshProof($nonce, $signedAt);
            $message = implode('|', ['exaearn-offline-recover:v1', $reference, $device->device_uuid, $nonce, (string) $signedAt]);
            if (!$this->crypto->verifyDeviceProof((string) $device->public_key, $message, (string) $proof['signature'])) {
                throw new OfflinePaymentException('device_proof_invalid', 'Device recovery could not be verified.', 403);
            }
            $this->consumeNonce((int) $user->id, (string) $device->device_uuid, $nonce, 'recover', $signedAt);
            $device->last_seen_at = now();
            $device->save();
            $this->audit->log((int) $user->id, 'exapay.offline.instrument.recovered', request(), ['instrument_id' => $instrument->instrument_uuid, 'reference' => $reference, 'device_id' => $device->id]);

            return $this->instrumentResponse($instrument, $this->crypto->issuerPublicKey());
        }, 3);
    }

    public function submitJournal(User $user, array $input): array
    {
        $this->assertEnabled();

        return DB::transaction(function () use ($user, $input): array {
            $policy = $this->policy(true);
            if (!$policy->enabled) throw new OfflinePaymentException('offline_simulation_disabled', 'Offline simulation is currently disabled.', 403);
            $this->assertSignedPolicy($policy);
            $device = $this->activeDevice($user, (string) $input['device_uuid']);
            $contract = new \App\Domain\ExaPay\Offline\OfflineJournalContract(
                (string) $device->device_uuid, (int) $input['counter'], (string) ($input['previous_digest'] ?? ''),
                (string) $input['nonce'], (int) $input['signed_at'], (array) $input['consumption_claims'],
                (string) $input['signature'], (int) ($input['protocol_version'] ?? 1),
            );
            $this->assertFreshProof($contract->nonce, $contract->signedAt);
            foreach ($contract->consumptionClaims as $claim) {
                if (!is_array($claim) || !isset($claim['instrument_id'], $claim['claim_id'], $claim['consumed_at'])
                    || (int) $claim['consumed_at'] > $contract->signedAt
                    || $contract->signedAt - (int) $claim['consumed_at'] > 2592000) {
                    throw new OfflinePaymentException('journal_claim_invalid', 'The signed journal contains an invalid consumption claim.', 422);
                }
            }
            if (count($contract->consumptionClaims) > (int) $policy->max_transfers_per_journal) {
                throw new OfflinePaymentException('journal_claim_limit_exceeded', 'The signed risk policy limits the number of transfer claims in this journal.', 422);
            }
            if ($contract->protocolVersion !== (int) config('exapay_offline.protocol_version', 1) || $contract->counter < 1) {
                throw new OfflinePaymentException('journal_protocol_invalid', 'The device journal protocol or counter is not supported.', 422);
            }
            $canonical = $contract->canonicalPayload();
            if (strlen($canonical) > (int) $policy->max_journal_bytes) {
                throw new OfflinePaymentException('journal_capacity_exceeded', 'The signed journal exceeds the active byte limit.', 422);
            }
            if (!$this->crypto->verifyDeviceProof((string) $device->public_key, $canonical, $contract->signature)) {
                throw new OfflinePaymentException('journal_signature_invalid', 'The signed device journal could not be verified.', 403);
            }
            $nonceDigest = hash('sha256', $contract->nonce);
            $eventDigest = hash('sha256', $canonical);
            $existing = \App\Models\ExaPayOfflineJournalSubmission::query()->where('device_id', $device->id)->where('device_counter', $contract->counter)->lockForUpdate()->first();
            if ($existing) {
                if (hash_equals((string) $existing->event_digest, $eventDigest)) {
                    return $this->journalResponse($existing);
                }
                throw new OfflinePaymentException('journal_counter_conflict', 'This device journal counter already contains a different signed event.', 409);
            }

            $expectedCounter = (int) $device->journal_counter + 1;
            $expectedPrevious = (string) ($device->last_journal_digest ?? '');
            $conflictCode = $contract->counter !== $expectedCounter ? 'counter_gap' : ($contract->previousDigest !== $expectedPrevious ? 'lineage_mismatch' : null);
            $controlEpoch = (int) \App\Models\ExaPayOfflineSecurityControl::query()->whereKey(1)->value('trust_epoch');
            if ((int) $device->trust_epoch < max((int) $policy->trust_epoch, $controlEpoch)) {
                $conflictCode = 'stale_trust_epoch';
                app(OfflineDeviceTrustService::class)->recordFraud('STALE_DEVICE_TRUST_EPOCH', 'HIGH', (int) $user->id, (int) $device->id, [
                    'journal_counter' => $contract->counter, 'device_trust_epoch' => (int) $device->trust_epoch,
                    'current_trust_epoch' => max((int) $policy->trust_epoch, $controlEpoch),
                ]);
            } elseif ($contract->counter < $expectedCounter) {
                app(OfflineDeviceTrustService::class)->recordFraud('DEVICE_COUNTER_ROLLBACK', 'CRITICAL', (int) $user->id, (int) $device->id, [
                    'submitted_counter' => $contract->counter, 'expected_counter' => $expectedCounter,
                ]);
            }
            if ($conflictCode === null && count($contract->consumptionClaims) > (int) $policy->max_transactions_per_device - (int) $device->transaction_count) {
                $conflictCode = 'transaction_limit_exceeded';
                app(OfflineDeviceTrustService::class)->recordFraud('DEVICE_TRANSACTION_LIMIT_EXCEEDED', 'HIGH', (int) $user->id, (int) $device->id, [
                    'submitted_count' => count($contract->consumptionClaims), 'accepted_count' => (int) $device->transaction_count,
                    'policy_limit' => (int) $policy->max_transactions_per_device,
                ]);
            }
            $submission = \App\Models\ExaPayOfflineJournalSubmission::query()->create([
                'journal_uuid' => (string) Str::uuid(), 'device_id' => $device->id, 'device_counter' => $contract->counter,
                'previous_digest' => $contract->previousDigest ?: null, 'event_digest' => $eventDigest, 'nonce_digest' => $nonceDigest,
                'payload' => ['protocol_version' => $contract->protocolVersion, 'consumption_claims' => $contract->consumptionClaims, 'signed_at' => $contract->signedAt],
                'device_signature' => $contract->signature, 'status' => $conflictCode ? 'conflict' : 'pending_review',
                'conflict_code' => $conflictCode, 'submitted_at' => now(),
            ]);
            $this->consumeNonce((int) $user->id, (string) $device->device_uuid, $contract->nonce, 'journal', $contract->signedAt);

            if ($conflictCode === null) {
                $claimsToInsert = [];
                $claimedInstrumentIds = [];
                foreach ($contract->consumptionClaims as $claim) {
                    $instrument = ExaPayOfflineInstrument::query()->where('instrument_uuid', $claim['instrument_id'] ?? '')->where('device_id', $device->id)->lockForUpdate()->first();
                    if (!$instrument) {
                        $submission->status = 'conflict';
                        $submission->conflict_code = 'instrument_lineage_mismatch';
                        $submission->save();
                        break;
                    }
                    $alreadyClaimed = in_array($instrument->id, $claimedInstrumentIds, true)
                        || DB::table('exapay_offline_consumption_claims')->where('instrument_id', $instrument->id)->lockForUpdate()->exists();
                    if ($alreadyClaimed) {
                        $submission->status = 'conflict';
                        $submission->conflict_code = 'duplicate_consumption_claim';
                        $submission->save();
                        break;
                    }
                    $claimedInstrumentIds[] = $instrument->id;
                    $claimsToInsert[] = ['claim' => $claim, 'instrument' => $instrument];
                }
                if ($submission->status === 'pending_review') {
                    foreach ($claimsToInsert as $row) {
                        DB::table('exapay_offline_consumption_claims')->insert([
                            'claim_uuid' => (string) ($row['claim']['claim_id'] ?? Str::uuid()), 'instrument_id' => $row['instrument']->id,
                            'journal_submission_id' => $submission->id, 'device_id' => $device->id,
                            'claimed_consumed_at' => now()->setTimestamp((int) $row['claim']['consumed_at']),
                            'status' => 'claimed_pending_reconciliation', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                if ($conflictCode === null) {
                    $device->journal_counter = $contract->counter;
                    $device->transaction_count = (int) $device->transaction_count + count($contract->consumptionClaims);
                    $device->last_journal_digest = $eventDigest;
                    $device->last_seen_at = now();
                    $device->last_counter_accepted_at = now();
                    $device->save();
                }
            }
            $this->audit->log((int) $user->id, 'exapay.offline.journal.submitted', request(), [
                'journal_id' => $submission->journal_uuid, 'device_id' => $device->id, 'status' => $submission->status,
                'conflict_code' => $submission->conflict_code, 'claim_count' => count($contract->consumptionClaims),
                'redemption_executed' => false,
            ]);

            return $this->journalResponse($submission);
        }, 3);
    }

    public function suspendDevice(int $deviceId, string $reason): ExaPayOfflineDevice
    {
        return DB::transaction(function () use ($deviceId, $reason): ExaPayOfflineDevice {
            $device = ExaPayOfflineDevice::query()->lockForUpdate()->findOrFail($deviceId);
            $device->status = 'suspended';
            $device->suspended_at = now();
            $device->suspension_reason = $reason;
            $device->revoked_at = now();
            $device->save();
            ExaPayOfflineDeviceKeyHistory::query()->where('device_id', $device->id)->where('status', 'active')
                ->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);
            ExaPayOfflineInstrument::query()->where('device_id', $device->id)
                ->whereNotIn('status', [OfflineInstrumentState::REDEEMED->value, OfflineInstrumentState::FAILED->value])
                ->update(['status' => OfflineInstrumentState::SUSPENDED->value, 'updated_at' => now()]);
            \App\Models\ExaPayOfflineValueOutput::query()->where('owner_device_id', $device->id)->where('status', 'available')
                ->update(['status' => 'quarantined', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);

            return $device->refresh();
        }, 3);
    }

    public function rotateDeviceKey(User $user, string $deviceUuid, string $newPublicKey, int $nextVersion, string $nonce, int $signedAt, string $signature): ExaPayOfflineDevice
    {
        $this->assertEnabled();
        $newPublicKey = trim($newPublicKey);
        $this->crypto->publicKeyDetails($newPublicKey);
        $newFingerprint = hash('sha256', preg_replace('/\\s+/', '', $newPublicKey) ?: $newPublicKey);

        return DB::transaction(function () use ($user, $deviceUuid, $newPublicKey, $nextVersion, $nonce, $signedAt, $signature, $newFingerprint): ExaPayOfflineDevice {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $device = ExaPayOfflineDevice::query()->where('user_id', $user->id)->where('device_uuid', strtolower($deviceUuid))->lockForUpdate()->firstOrFail();
            if ($device->status !== 'active' || $nextVersion !== (int) $device->key_version + 1) {
                throw new OfflinePaymentException('device_key_version_conflict', 'Only an active device can rotate to the next key version.', 409);
            }
            if (hash_equals((string) $device->public_key_fingerprint, $newFingerprint)) {
                throw new OfflinePaymentException('device_key_reuse', 'The new device key must differ from the current key.', 422);
            }
            $this->assertFreshProof($nonce, $signedAt);
            $message = implode('|', ['exaearn-offline-key-rotate:v1', $device->device_uuid, $newFingerprint, (string) $nextVersion, $nonce, (string) $signedAt]);
            if (!$this->crypto->verifyDeviceProof((string) $device->public_key, $message, $signature)) {
                throw new OfflinePaymentException('device_key_rotation_proof_invalid', 'The current enrolled key did not authorize rotation.', 403);
            }
            $this->consumeNonce((int) $user->id, (string) $device->device_uuid, $nonce, 'key_rotate', $signedAt);
            ExaPayOfflineDeviceKeyHistory::query()->where('device_id', $device->id)->where('status', 'active')->update([
                'status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now(),
            ]);
            ExaPayOfflineDeviceKeyHistory::query()->create([
                'device_id' => $device->id, 'key_version' => $nextVersion, 'public_key' => $newPublicKey,
                'public_key_fingerprint' => $newFingerprint, 'status' => 'active', 'valid_from' => now(),
            ]);
            $device->public_key = $newPublicKey;
            $device->public_key_fingerprint = $newFingerprint;
            $device->key_version = $nextVersion;
            $device->key_rotated_at = now();
            $device->last_seen_at = now();
            $device->save();
            $this->audit->log((int) $user->id, 'exapay.offline.device.key_rotated', request(), [
                'device_id' => $device->id, 'device_uuid' => $device->device_uuid, 'key_version' => $nextVersion,
                'new_key_fingerprint' => $newFingerprint, 'old_key_revoked' => true,
            ]);

            return $device->refresh();
        }, 3);
    }

    private function isEnabled(): bool
    {
        return (bool) config('exapay_offline.enabled', false)
            && OfflineEnvironmentGuard::allowsSimulation();
    }

    private function assertEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw new OfflinePaymentException('offline_simulation_disabled', 'Offline stored-value simulation is not available in this environment.', 404);
        }
    }

    private function policy(bool $lock = false): ExaPayOfflineRiskPolicy
    {
        $query = ExaPayOfflineRiskPolicy::query()->whereKey(1);
        if ($lock) $query->lockForUpdate();
        $policy = $query->first();
        if (!$policy) {
            throw new OfflinePaymentException('offline_policy_unavailable', 'Offline simulation policy is not configured.', 503);
        }

        return $policy;
    }

    private function activeDevice(User $user, string $deviceUuid): ExaPayOfflineDevice
    {
        $device = ExaPayOfflineDevice::query()->where('user_id', $user->id)->where('device_uuid', strtolower($deviceUuid))->lockForUpdate()->first();
        if (!$device || $device->status !== 'active') {
            throw new OfflinePaymentException('device_not_active', 'An active enrolled device is required.', 403);
        }

        return $device;
    }

    private function assertFreshProof(string $nonce, int $signedAt): void
    {
        $maxAge = max(30, (int) config('exapay_offline.proof_max_age_seconds', 300));
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $nonce) || abs(now()->timestamp - $signedAt) > $maxAge) {
            throw new OfflinePaymentException('device_proof_expired', 'The device proof is invalid or expired.', 422);
        }
    }

    private function consumeNonce(int $userId, string $deviceUuid, string $nonce, string $purpose, int $signedAt): void
    {
        $inserted = DB::table('exapay_offline_device_nonces')->insertOrIgnore([
            'user_id' => $userId, 'device_uuid' => strtolower($deviceUuid), 'nonce_digest' => hash('sha256', $nonce),
            'purpose' => $purpose, 'signed_at' => now()->setTimestamp($signedAt), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($inserted !== 1) {
            throw new OfflinePaymentException('device_nonce_replayed', 'This device proof has already been used.', 409);
        }
    }

    private function enrollmentMessage(int $userId, string $deviceUuid, string $nonce, int $signedAt): string
    {
        return implode('|', ['exaearn-offline-enroll:v1', (string) $userId, $deviceUuid, $nonce, (string) $signedAt]);
    }

    private function instrumentResponse(ExaPayOfflineInstrument $instrument, ?string $issuerPublicKey = null): array
    {
        return [
            'instrument_id' => $instrument->instrument_uuid,
            'reference' => $instrument->reference,
            'payment_mode' => 'OFFLINE_STORED_VALUE',
            'environment' => 'SIMULATION',
            'asset' => 'SIMUSDT',
            'amount_minor' => (int) $instrument->amount_minor,
            'status' => $instrument->status,
            'delivery_status' => $instrument->delivery_status,
            'payload' => $instrument->payload,
            'issuer_signature' => $instrument->issuer_signature,
            'issuer_public_key' => $issuerPublicKey ?? $instrument->issuer_public_key ?? $this->crypto->issuerPublicKey(),
            'signature_algorithm' => 'SHA256',
            'real_funds' => false,
        ];
    }

    private function journalResponse(\App\Models\ExaPayOfflineJournalSubmission $submission): array
    {
        return [
            'journal_id' => $submission->journal_uuid,
            'counter' => (int) $submission->device_counter,
            'event_digest' => $submission->event_digest,
            'status' => $submission->status,
            'conflict_code' => $submission->conflict_code,
            'redemption_status' => 'not_redeemed',
            'real_funds' => false,
        ];
    }

    private function minorToDecimal(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function accountingSnapshot(): array
    {
        $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->first();
        $issued = (int) ($reserve?->issued_minor ?? 0);
        $outstanding = (int) ($reserve?->outstanding_liability_minor ?? 0);
        $redeemed = (int) ($reserve?->redeemed_minor ?? 0);
        $extinguished = (int) ($reserve?->extinguished_minor ?? 0);

        return [
            'issued_minor' => $issued,
            'outstanding_offline_minor' => $outstanding,
            'redeemed_minor' => $redeemed,
            'legitimately_extinguished_minor' => $extinguished,
            'conserved' => $issued === $outstanding + $redeemed + $extinguished,
        ];
    }

    private function rootLineageDigest(string $instrumentUuid, array $payload): string
    {
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', 'exaearn-offline-root:v1|' . $instrumentUuid . '|' . hash('sha256', $canonical));
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

    private function assertSignedPolicy(ExaPayOfflineRiskPolicy $policy): void
    {
        $canonical = $this->policyContract($policy)->canonicalPayload();
        if (!$policy->policy_signature || !$policy->policy_public_key || !$policy->policy_digest
            || !hash_equals((string) $policy->policy_digest, hash('sha256', $canonical))
            || !$policy->policy_expires_at || $policy->policy_expires_at->isPast()
            || !$this->crypto->verifyIssuerPayload((string) $policy->policy_public_key, $canonical, (string) $policy->policy_signature)) {
            throw new OfflinePaymentException('risk_policy_signature_invalid', 'The offline risk policy is unsigned, expired, or invalid.', 503);
        }
        $trust = app(OfflineDeviceTrustService::class);
        if ($trust->latestPolicyVersion() !== (int) $policy->policy_version) {
            throw new OfflinePaymentException('risk_policy_downgrade_detected', 'The offline policy version is stale or has been rolled back.', 503);
        }
        $keyId = substr(hash('sha256', (string) $policy->policy_public_key), 0, 32);
        if (\App\Models\ExaPayOfflineIssuerKey::query()->where('key_id', $keyId)->where('status', 'COMPROMISED')->exists()) {
            throw new OfflinePaymentException('issuer_key_compromised', 'The signing key for this offline policy is compromised.', 503);
        }
    }

    private function signedPolicyForClient(?ExaPayOfflineRiskPolicy $policy): ?array
    {
        if (!$policy?->policy_signature || !$policy->policy_public_key || !$policy->policy_digest || !$policy->policy_expires_at || $policy->policy_expires_at->isPast()) return null;
        try {
            $canonical = $this->policyContract($policy)->canonicalPayload();
            if (!hash_equals((string) $policy->policy_digest, hash('sha256', $canonical))
                || !$this->crypto->verifyIssuerPayload((string) $policy->policy_public_key, $canonical, (string) $policy->policy_signature)) return null;
            if (app(OfflineDeviceTrustService::class)->latestPolicyVersion() !== (int) $policy->policy_version) return null;
            if (\App\Models\ExaPayOfflineIssuerKey::query()->where('key_id', substr(hash('sha256', (string) $policy->policy_public_key), 0, 32))->where('status', 'COMPROMISED')->exists()) return null;
            return ['payload' => json_decode($canonical, true, 512, JSON_THROW_ON_ERROR), 'digest' => $policy->policy_digest,
                'signature' => $policy->policy_signature, 'issuer_public_key' => $policy->policy_public_key];
        } catch (\Throwable) {
            return null;
        }
    }

    private function decimalToMinor(string $amount): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/', $amount, $matches)) return 0;
        $whole = (int) $matches[1];
        $fractionText = $matches[2] ?? '';
        if (strlen($fractionText) > 2 && trim(substr($fractionText, 2), '0') !== '') return 0;
        $fraction = (int) str_pad(substr($fractionText, 0, 2), 2, '0', STR_PAD_RIGHT);
        if ($whole > intdiv(PHP_INT_MAX - $fraction, 100)) return PHP_INT_MAX;

        return $whole * 100 + $fraction;
    }
}
