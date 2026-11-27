<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\ExaPay\Offline\OfflinePaymentException;
use App\Domain\ExaPay\Offline\OfflineIssuanceContract;
use App\Domain\ExaPay\Offline\OfflinePaymentMode;
use App\Models\User;
use App\Services\ExaPay\Offline\OfflineStoredValueService;
use App\Services\ExaPay\Offline\OfflineTransferReconciliationService;
use App\Services\ExaPay\Offline\OfflineReconciliationClaimService;
use App\Services\ExaPay\Offline\OfflineRedemptionService;
use App\Services\ExaPay\Offline\OfflineDeviceAttestationVerifier;
use App\Services\ExaPay\Offline\OfflineInstrumentCrypto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class ExaPayOfflineController extends Controller
{
    public function capabilities(OfflineStoredValueService $service): JsonResponse
    {
        return response()->json(['data' => $service->capabilities()]);
    }

    public function balance(Request $request, OfflineStoredValueService $service): JsonResponse
    {
        return $this->run(fn () => response()->json(['data' => $service->balanceOverview($this->user($request))]));
    }

    public function enroll(Request $request, OfflineStoredValueService $service, OfflineInstrumentCrypto $crypto): JsonResponse
    {
        $payload = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'public_key' => ['required', 'string', 'min:80', 'max:4096'],
            'nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'],
            'signed_at' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);

        return $this->run(function () use ($request, $service, $crypto, $payload): JsonResponse {
            $device = $service->enrollDevice($this->user($request), $payload);
            $now = now();
            $policy = \App\Models\ExaPayOfflineRiskPolicy::query()->firstOrFail();
            $certificate = $crypto->signIssuerPayload([
                'certificate_version' => 1,
                'device_uuid' => strtolower((string) $device->device_uuid),
                'public_key' => (string) $device->public_key,
                'key_version' => (int) $device->key_version,
                'protocol_version' => (int) $device->protocol_version,
                'trust_class' => (string) $device->trust_class,
                'trust_epoch' => (int) $device->trust_epoch,
                'asset' => 'SIMUSDT',
                'simulation_only' => true,
                'issued_at' => $now->timestamp,
                'expires_at' => $now->copy()->addSeconds(max(60, (int) $policy->instrument_lifetime_seconds))->timestamp,
            ]);

            return response()->json(['data' => [
                'device_uuid' => $device->device_uuid,
                'status' => $device->status,
                'protocol_version' => $device->protocol_version,
                'hardware_security_level' => 'software_key; secure-element attestation unavailable',
                'real_funds_enabled' => false,
                'device_certificate' => [
                    'canonical_payload' => $certificate['canonical_payload'],
                    'signature' => $certificate['signature'],
                    'issuer_public_key' => $certificate['issuer_public_key'],
                    'signature_algorithm' => $certificate['signature_algorithm'],
                ],
            ]], 201);
        });
    }

    public function rotateDeviceKey(Request $request, string $deviceUuid, OfflineStoredValueService $service): JsonResponse
    {
        $payload = $request->validate([
            'public_key' => ['required', 'string', 'min:80', 'max:4096'],
            'key_version' => ['required', 'integer', 'min:2'],
            'nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'],
            'signed_at' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);
        return $this->run(function () use ($request, $deviceUuid, $service, $payload): JsonResponse {
            $device = $service->rotateDeviceKey($this->user($request), $deviceUuid, $payload['public_key'], (int) $payload['key_version'], $payload['nonce'], (int) $payload['signed_at'], $payload['signature']);
            return response()->json(['data' => [
                'device_uuid' => $device->device_uuid, 'key_version' => $device->key_version,
                'key_rotated_at' => $device->key_rotated_at?->toIso8601String(),
                'old_key_revoked' => true, 'real_funds_enabled' => false,
            ]]);
        });
    }

    public function attestDevice(Request $request, string $deviceUuid, OfflineStoredValueService $service, OfflineDeviceAttestationVerifier $verifier): JsonResponse
    {
        $payload = $request->validate([
            'format' => ['required', 'in:android_keystore,strongbox,ios_app_attest,ios_secure_enclave,javacard,secure_element'],
            'challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/'],
            'attestation' => ['sometimes', 'array'],
        ]);
        if (strlen(json_encode($payload['attestation'] ?? [], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) > 16384) {
            return response()->json(['error_code' => 'attestation_payload_too_large'], 422);
        }
        return $this->run(function () use ($request, $deviceUuid, $service, $verifier, $payload): JsonResponse {
            $user = $this->user($request);
            $key = $this->attestationChallengeCacheKey((int) $user->id, strtolower($deviceUuid));
            $challengeValid = Cache::lock($key.':consume', 5)->block(2, function () use ($key, $payload): bool {
                $expected = Cache::get($key);
                if (!is_string($expected) || !hash_equals($expected, $payload['challenge'])) {
                    return false;
                }
                Cache::forget($key);
                return true;
            });
            if (!$challengeValid) {
                return response()->json(['error_code' => 'attestation_challenge_expired_or_invalid'], 409);
            }
            $result = $service->recordAttestation($user, $deviceUuid, $payload['challenge'], $payload['format'], (array) ($payload['attestation'] ?? []), $verifier);
            return response()->json(['data' => $result], ($result['attestation_status'] === 'VERIFIED') ? 200 : 202);
        });
    }

    public function createAttestationChallenge(Request $request, string $deviceUuid): JsonResponse
    {
        $user = $this->user($request);
        $device = \App\Models\ExaPayOfflineDevice::query()
            ->where('user_id', $user->id)
            ->where('device_uuid', strtolower($deviceUuid))
            ->firstOrFail();
        if ($device->status !== 'active') {
            return response()->json(['error_code' => 'device_suspended'], 403);
        }
        $challenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Cache::put($this->attestationChallengeCacheKey((int) $user->id, strtolower($deviceUuid)), $challenge, now()->addMinutes(5));
        return response()->json(['data' => [
            'device_uuid' => strtolower($deviceUuid), 'challenge' => $challenge,
            'expires_in_seconds' => 300, 'real_funds_enabled' => false,
        ]]);
    }

    private function attestationChallengeCacheKey(int $userId, string $deviceUuid): string
    {
        return 'exapay:offline:attestation-challenge:'.$userId.':'.$deviceUuid;
    }

    public function issue(Request $request, OfflineStoredValueService $service): JsonResponse
    {
        $payload = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'asset' => ['required', 'in:SIMUSDT'],
        ]);
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if (!preg_match('/^[A-Za-z0-9._:-]{16,160}$/', $idempotencyKey)) {
            return response()->json(['error_code' => 'idempotency_key_required', 'message' => 'A valid Idempotency-Key header is required.'], 422);
        }

        return $this->run(function () use ($request, $service, $payload, $idempotencyKey): JsonResponse {
            $contract = new OfflineIssuanceContract(
                OfflinePaymentMode::OFFLINE_STORED_VALUE, (string) $payload['device_uuid'],
                (int) $payload['amount_minor'], 'SIMUSDT', $idempotencyKey,
            );
            $data = $service->issue($this->user($request), $contract);

            return response()->json(['data' => $data], ($data['idempotent_replay'] ?? false) ? 200 : 201);
        });
    }

    public function status(Request $request, string $reference, OfflineStoredValueService $service): JsonResponse
    {
        return $this->run(fn () => response()->json(['data' => $service->status($this->user($request), $reference)]));
    }

    public function acknowledge(Request $request, string $reference, OfflineStoredValueService $service): JsonResponse
    {
        $proof = $request->validate([
            'device_uuid' => ['required', 'uuid'], 'nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'],
            'signed_at' => ['required', 'integer', 'min:1'], 'signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);

        return $this->run(fn () => response()->json(['data' => $service->acknowledge($this->user($request), $reference, $proof)]));
    }

    public function recover(Request $request, string $reference, OfflineStoredValueService $service): JsonResponse
    {
        $proof = $request->validate([
            'device_uuid' => ['required', 'uuid'], 'nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'],
            'signed_at' => ['required', 'integer', 'min:1'], 'signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);

        return $this->run(fn () => response()->json(['data' => $service->recover($this->user($request), $reference, $proof)]));
    }

    public function submitJournal(Request $request, OfflineStoredValueService $service): JsonResponse
    {
        $payload = $request->validate([
            'device_uuid' => ['required', 'uuid'], 'counter' => ['required', 'integer', 'min:1'],
            'previous_digest' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'protocol_version' => ['required', 'integer', 'min:1', 'max:1'],
            'nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'], 'signed_at' => ['required', 'integer', 'min:1'],
            'consumption_claims' => ['required', 'array', 'max:100'],
            'consumption_claims.*.instrument_id' => ['required', 'uuid'],
            'consumption_claims.*.claim_id' => ['required', 'uuid'],
            'consumption_claims.*.consumed_at' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);

        return $this->run(fn () => response()->json(['data' => $service->submitJournal($this->user($request), $payload)], 202));
    }

    public function reconcileTransfers(Request $request, OfflineReconciliationClaimService $service): JsonResponse
    {
        $payload = $request->validate([
            'transfers' => ['required', 'array', 'min:1', 'max:100'],
            'transfers.*.transfer_uuid' => ['required', 'uuid'],
            'transfers.*.sender_device_uuid' => ['required', 'uuid'],
            'transfers.*.recipient_device_uuid' => ['required', 'uuid'],
            'transfers.*.sender_key_version' => ['required', 'integer', 'min:1'],
            'transfers.*.recipient_key_version' => ['required', 'integer', 'min:1'],
            'transfers.*.input_output_uuid' => ['required', 'uuid'],
            'transfers.*.recipient_output_uuid' => ['required', 'uuid'],
            'transfers.*.change_output_uuid' => ['nullable', 'uuid'],
            'transfers.*.input_amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'transfers.*.transfer_amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'transfers.*.change_amount_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'transfers.*.asset' => ['required', 'in:SIMUSDT'],
            'transfers.*.transfer_counter' => ['required', 'integer', 'min:1'],
            'transfers.*.protocol_version' => ['required', 'integer', 'in:1'],
            'transfers.*.hop_count' => ['required', 'integer', 'min:1', 'max:100'],
            'transfers.*.nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'],
            'transfers.*.signed_at' => ['required', 'integer', 'min:1'],
            'transfers.*.parent_digest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'transfers.*.idempotency_key' => ['required', 'string', 'min:16', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'transfers.*.sender_signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/=]+$/'],
            'transfers.*.recipient_signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/=]+$/'],
        ]);

        return $this->run(function () use ($request, $service, $payload): JsonResponse {
            $final = $payload['transfers'][count($payload['transfers']) - 1];
            $idempotencyKey = (string) $request->header('Idempotency-Key', 'legacy-reconcile:' . $final['transfer_uuid']);
            $result = $service->submit($this->user($request), $idempotencyKey,
                (string) $final['recipient_output_uuid'], (int) $final['transfer_amount_minor'], $payload['transfers']);
            $statusCode = in_array($result['status'], ['CONFLICT_DETECTED', 'REJECTED'], true) ? 409 : 202;
            return response()->json(['data' => $result], $statusCode);
        });
    }

    public function createReconciliationClaim(Request $request, OfflineReconciliationClaimService $service): JsonResponse
    {
        $payload = $request->validate([
            'expected_output_uuid' => ['required', 'uuid'],
            'expected_amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'transfers' => ['sometimes', 'array', 'max:100'],
        ] + $this->transferValidationRules());
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if (!preg_match('/^[A-Za-z0-9._:-]{16,160}$/', $idempotencyKey)) {
            return response()->json(['error_code' => 'idempotency_key_required', 'message' => 'A valid Idempotency-Key header is required.'], 422);
        }

        return $this->run(function () use ($request, $service, $payload, $idempotencyKey): JsonResponse {
            $result = $service->submit(
            $this->user($request), $idempotencyKey, (string) $payload['expected_output_uuid'],
            (int) $payload['expected_amount_minor'], (array) ($payload['transfers'] ?? []),
            );
            return response()->json(['data' => $result], in_array($result['status'], ['CONFLICT_DETECTED', 'REJECTED'], true) ? 409 : 202);
        });
    }

    public function appendReconciliationEvidence(Request $request, string $claimUuid, OfflineReconciliationClaimService $service): JsonResponse
    {
        $payload = $request->validate(['transfers' => ['required', 'array', 'min:1', 'max:100']] + $this->transferValidationRules());
        return $this->run(function () use ($request, $service, $claimUuid, $payload): JsonResponse {
            $result = $service->appendEvidence($this->user($request), $claimUuid, $payload['transfers']);
            return response()->json(['data' => $result], in_array($result['status'], ['CONFLICT_DETECTED', 'REJECTED'], true) ? 409 : 202);
        });
    }

    public function reconciliationClaims(Request $request, OfflineReconciliationClaimService $service): JsonResponse
    {
        return response()->json(['data' => $service->history($this->user($request), (int) $request->query('per_page', 25))]);
    }

    public function reconciliationClaim(Request $request, string $claimUuid, OfflineReconciliationClaimService $service): JsonResponse
    {
        return $this->run(fn () => response()->json(['data' => $service->show($this->user($request), $claimUuid)]));
    }

    public function redeemReconciliationClaim(Request $request, string $claimUuid, OfflineRedemptionService $service): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if (!preg_match('/^[A-Za-z0-9._:-]{16,160}$/', $idempotencyKey)) {
            return response()->json(['error_code' => 'idempotency_key_required', 'message' => 'A valid Idempotency-Key header is required.'], 422);
        }
        return $this->run(fn () => response()->json(['data' => $service->redeem($this->user($request), $claimUuid, $idempotencyKey)]));
    }

    public function redemptions(Request $request, OfflineRedemptionService $service): JsonResponse
    {
        return response()->json(['data' => $service->history($this->user($request), (int) $request->query('per_page', 25))]);
    }

    public function redemption(Request $request, string $redemptionUuid, OfflineRedemptionService $service): JsonResponse
    {
        return $this->run(fn () => response()->json(['data' => $service->show($this->user($request), $redemptionUuid)]));
    }

    private function transferValidationRules(): array
    {
        return [
            'transfers.*.transfer_uuid' => ['required', 'uuid'],
            'transfers.*.sender_device_uuid' => ['required', 'uuid'],
            'transfers.*.recipient_device_uuid' => ['required', 'uuid'],
            'transfers.*.sender_key_version' => ['required', 'integer', 'min:1'],
            'transfers.*.recipient_key_version' => ['required', 'integer', 'min:1'],
            'transfers.*.input_output_uuid' => ['required', 'uuid'],
            'transfers.*.recipient_output_uuid' => ['required', 'uuid'],
            'transfers.*.change_output_uuid' => ['nullable', 'uuid'],
            'transfers.*.input_amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'transfers.*.transfer_amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'transfers.*.change_amount_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'transfers.*.asset' => ['required', 'in:SIMUSDT'],
            'transfers.*.transfer_counter' => ['required', 'integer', 'min:1'],
            'transfers.*.protocol_version' => ['required', 'integer', 'in:1'],
            'transfers.*.hop_count' => ['required', 'integer', 'min:1', 'max:100'],
            'transfers.*.nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{32,128}$/'],
            'transfers.*.signed_at' => ['required', 'integer', 'min:1'],
            'transfers.*.parent_digest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'transfers.*.idempotency_key' => ['required', 'string', 'min:16', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'transfers.*.sender_signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/]+=*$/'],
            'transfers.*.recipient_signature' => ['required', 'string', 'max:2048', 'regex:/^[A-Za-z0-9+\/]+=*$/'],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function run(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (OfflinePaymentException $exception) {
            return response()->json(['error_code' => $exception->errorCode, 'message' => $exception->getMessage()], $exception->httpStatus);
        }
    }
}
