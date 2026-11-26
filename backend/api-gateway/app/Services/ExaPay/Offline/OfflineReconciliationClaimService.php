<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflinePaymentException;
use App\Domain\ExaPay\Offline\OfflineReconciliationClaimState as ClaimState;
use App\Domain\ExaPay\Offline\OfflineRiskPolicyContract;
use App\Domain\ExaPay\Offline\OfflineTransferCommitment;
use App\Models\ExaPayOfflineDevice;
use App\Models\ExaPayOfflineReconciliationClaim;
use App\Models\ExaPayOfflineReconciliationEvidence;
use App\Models\ExaPayOfflineReconciliationEvent;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Models\ExaPayOfflineTransferSession;
use App\Models\ExaPayOfflineValueOutput;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ExaPay\Offline\OfflineInstrumentCrypto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Durable evidence inbox and resumable verification for recipient-submitted offline chains. */
final class OfflineReconciliationClaimService
{
    public function __construct(
        private readonly OfflineTransferReconciliationService $transfers,
        private readonly OfflineInstrumentCrypto $crypto,
        private readonly AuditLogService $audit,
    ) {
    }

    public function submit(User $user, string $idempotencyKey, string $outputUuid, int $amountMinor, array $evidence): array
    {
        $this->assertSimulation();
        if (!preg_match('/^[A-Za-z0-9._:-]{16,160}$/', $idempotencyKey) || $amountMinor < 1 || count($evidence) > 100) {
            throw new OfflinePaymentException('reconciliation_claim_invalid', 'The claim amount, idempotency key, or evidence count is invalid.', 422);
        }
        $intentDigest = hash('sha256', json_encode([
            'protocol_version' => 1, 'user_id' => (int) $user->id,
            'expected_output_uuid' => strtolower($outputUuid), 'expected_amount_minor' => $amountMinor, 'asset' => 'SIMUSDT',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $claimUuid = DB::transaction(function () use ($user, $idempotencyKey, $outputUuid, $amountMinor, $intentDigest, $evidence): string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $claim = ExaPayOfflineReconciliationClaim::query()
                ->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($claim) {
                if (!hash_equals((string) $claim->request_digest, $intentDigest)) {
                    throw new OfflinePaymentException('reconciliation_idempotency_conflict', 'This idempotency key is already bound to a different claim.', 409);
                }
            } else {
                $claim = ExaPayOfflineReconciliationClaim::query()->create([
                    'claim_uuid' => (string) Str::uuid(), 'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey, 'request_digest' => $intentDigest,
                    'expected_output_uuid' => strtolower($outputUuid), 'expected_amount_minor' => $amountMinor,
                    'status' => ClaimState::SUBMITTED->value,
                ]);
                $this->appendEvent($claim, null, ClaimState::SUBMITTED->value, 'claim_submitted', 'user', (int) $user->id, ['evidence_count' => 0]);
            }

            if ($claim->status === ClaimState::REJECTED->value) {
                return (string) $claim->claim_uuid;
            }
            $this->storeEvidence($claim, $evidence);

            return (string) $claim->claim_uuid;
        }, 3);

        return $this->evaluate($user, $claimUuid);
    }

    public function appendEvidence(User $user, string $claimUuid, array $evidence): array
    {
        $this->assertSimulation();
        if ($evidence === [] || count($evidence) > 100) {
            throw new OfflinePaymentException('reconciliation_evidence_invalid', 'Submit between one and one hundred signed transfers.', 422);
        }
        DB::transaction(function () use ($user, $claimUuid, $evidence): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $claim = ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $claimUuid)->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$claim) throw new OfflinePaymentException('reconciliation_claim_not_found', 'The claim was not found for this account.', 404);
            if ($claim->status === ClaimState::REJECTED->value) {
                throw new OfflinePaymentException('reconciliation_claim_closed', 'This claim no longer accepts evidence.', 409);
            }
            $this->storeEvidence($claim, $evidence);
        }, 3);

        return $this->evaluate($user, $claimUuid);
    }

    public function evaluate(User $user, string $claimUuid): array
    {
        $this->assertSimulation();

        return DB::transaction(function () use ($user, $claimUuid): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $claim = ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $claimUuid)->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$claim) throw new OfflinePaymentException('reconciliation_claim_not_found', 'The claim was not found for this account.', 404);
            if (in_array($claim->status, [ClaimState::VERIFIED->value, ClaimState::REDEMPTION_PENDING->value, ClaimState::REDEEMED->value, ClaimState::CONFLICT_DETECTED->value, ClaimState::REJECTED->value, ClaimState::RECOVERY_REQUIRED->value], true)) {
                return $this->response($claim);
            }
            $this->transition($claim, ClaimState::VALIDATING, 'validation_started', null, null, null, ['evidence_count' => $claim->evidence()->count()]);
            try {
                $this->assertSignedPolicy($claim);
            } catch (OfflinePaymentException $exception) {
                return $this->failClaim($claim, ClaimState::PENDING, $exception->errorCode, $exception->getMessage());
            }
            $entries = $this->evidenceEntries($claim);
            if (count($entries) > 100) {
                return $this->failClaim($claim, ClaimState::REJECTED, 'evidence_limit_exceeded', 'The claim has too many transfer records.');
            }

            $ordered = $this->orderedPath($claim, $entries);
            if ($ordered === null) {
                $claim->evaluated_evidence_revision = (int) $claim->evidence_revision;
                $claim->save();
                $this->transition($claim, ClaimState::EVIDENCE_INCOMPLETE, 'evidence_incomplete', null, null, null, ['evidence_count' => count($entries)]);
                return $this->response($claim->fresh());
            }
            if ($ordered === false) {
                return $this->failClaim($claim, ClaimState::CONFLICT_DETECTED, 'evidence_lineage_conflict', 'The submitted evidence conflicts with the claim output or lineage.');
            }

            try {
                if ($ordered !== []) {
                    $result = $this->transfers->reconcile($user, $ordered);
                    if (($result['status'] ?? null) === 'conflict_quarantined') {
                        return $this->failClaim($claim, ClaimState::CONFLICT_DETECTED, 'double_spend_quarantined', 'Conflicting spend evidence was quarantined.');
                    }
                }
                $output = ExaPayOfflineValueOutput::query()->where('output_uuid', $claim->expected_output_uuid)->lockForUpdate()->first();
                if (!$output || (int) $output->owner_user_id !== (int) $user->id || (int) $output->amount_minor !== (int) $claim->expected_amount_minor) {
                    return $this->failClaim($claim, ClaimState::CONFLICT_DETECTED, 'claim_output_mismatch', 'The claimed output does not match the authenticated recipient and amount.');
                }
                if ($output->status !== 'available') {
                    return $this->failClaim($claim, ClaimState::CONFLICT_DETECTED, 'claim_output_unavailable', 'The claimed offline output is spent, quarantined, or otherwise unavailable.');
                }
                $this->transfers->verifyOutputLineage($output);
                $claim->result = ['asset' => 'SIMUSDT', 'output_uuid' => $output->output_uuid, 'amount_minor' => (int) $output->amount_minor, 'online_credit' => false];
                $claim->verified_at = now();
                $claim->evaluated_evidence_revision = (int) $claim->evidence_revision;
                $claim->reason_code = null;
                $claim->save();
                $claim->evidence()->where('status', 'SUBMITTED')->update(['status' => 'VERIFIED', 'updated_at' => now()]);
                $this->transition($claim, ClaimState::VERIFIED, 'claim_verified', null, null, null, ['evidence_count' => count($ordered), 'online_credit' => false]);
                $this->audit->log((int) $user->id, 'exapay.offline.reconciliation.verified', request(), [
                    'claim_uuid' => $claim->claim_uuid, 'output_uuid' => $output->output_uuid,
                    'amount_minor' => (int) $output->amount_minor, 'simulation_only' => true, 'online_credit' => false,
                ]);

                return $this->response($claim->fresh());
            } catch (OfflinePaymentException $exception) {
                $fraudReason = match ($exception->errorCode) {
                    'transfer_sender_signature_invalid', 'transfer_recipient_proof_invalid', 'transfer_issuer_lineage_invalid' => 'FORGED_TRANSFER_OR_ISSUER_PROOF',
                    'transfer_nonce_replayed' => 'REPLAYED_TRANSFER_NONCE',
                    'transfer_counter_conflict', 'journal_counter_conflict' => 'DEVICE_COUNTER_ROLLBACK',
                    'device_trust_epoch_stale' => 'STALE_DEVICE_TRUST_EPOCH',
                    'device_spending_limit_exceeded', 'transfer_risk_limit_exceeded', 'journal_capacity_exceeded' => 'RISK_POLICY_VIOLATION',
                    default => null,
                };
                if ($fraudReason !== null) {
                    app(OfflineDeviceTrustService::class)->recordFraud($fraudReason, $fraudReason === 'DEVICE_COUNTER_ROLLBACK' ? 'CRITICAL' : 'HIGH', (int) $user->id, null, [
                        'claim_uuid' => $claim->claim_uuid, 'reason_code' => $exception->errorCode,
                    ]);
                }
                $state = $exception->httpStatus >= 500 ? ClaimState::PENDING : ($exception->httpStatus === 409 ? ClaimState::CONFLICT_DETECTED : ClaimState::REJECTED);
                return $this->failClaim($claim, $state, $exception->errorCode, $exception->getMessage());
            }
        }, 3);
    }

    public function show(User $user, string $claimUuid): array
    {
        $this->assertSimulation();
        $claim = ExaPayOfflineReconciliationClaim::query()->where('user_id', $user->id)->where('claim_uuid', $claimUuid)->first();
        if (!$claim) throw new OfflinePaymentException('reconciliation_claim_not_found', 'The claim was not found for this account.', 404);

        return $this->response($claim);
    }

    public function history(User $user, int $perPage = 25): array
    {
        $this->assertSimulation();
        return ExaPayOfflineReconciliationClaim::query()->where('user_id', $user->id)->latest('id')->paginate(min(100, max(1, $perPage)))->toArray();
    }

    public function adminList(?string $status = null, int $perPage = 50): array
    {
        $query = ExaPayOfflineReconciliationClaim::query()->latest('id');
        if ($status !== null) $query->where('status', $status);
        return $query->paginate(min(100, max(1, $perPage)))->toArray();
    }

    private function storeEvidence(ExaPayOfflineReconciliationClaim $claim, array $entries): void
    {
        foreach ($entries as $entry) {
            $commitment = $this->commitment($entry);
            $canonical = $commitment->canonicalPayload();
            $digest = $commitment->digest();
            $existing = ExaPayOfflineReconciliationEvidence::query()->where('claim_id', $claim->id)
                ->where('transfer_uuid', $commitment->transferUuid)->where('transfer_digest', $digest)->first();
            if ($existing) continue;

            $sameTransfer = ExaPayOfflineReconciliationEvidence::query()->where('claim_id', $claim->id)
                ->where('transfer_uuid', $commitment->transferUuid)->first();
            if (!$sameTransfer && in_array($claim->status, [ClaimState::VERIFIED->value, ClaimState::REDEEMED->value, ClaimState::CONFLICT_DETECTED->value, ClaimState::REJECTED->value], true)) {
                throw new OfflinePaymentException('reconciliation_claim_closed', 'A verified or closed claim accepts only a conflicting replay of previously submitted evidence.', 409);
            }
            ExaPayOfflineReconciliationEvidence::query()->create([
                'claim_id' => $claim->id, 'transfer_uuid' => $commitment->transferUuid,
                'transfer_digest' => $digest, 'input_output_uuid' => $commitment->inputOutputUuid,
                'parent_digest' => $commitment->parentDigest, 'canonical_payload' => $commitment->canonicalArray(),
                'sender_signature' => (string) ($entry['sender_signature'] ?? ''),
                'recipient_signature' => (string) ($entry['recipient_signature'] ?? ''),
                'status' => $sameTransfer ? 'CONFLICT' : 'SUBMITTED',
            ]);
            $claim->evidence_revision = (int) $claim->evidence_revision + 1;
            $claim->save();
            $this->appendEvent($claim, $claim->status, $claim->status, $sameTransfer ? 'conflicting_transfer_evidence' : 'evidence_appended', 'user', (int) $claim->user_id, [
                'transfer_uuid' => $commitment->transferUuid, 'transfer_digest' => $digest,
            ]);
            if ($sameTransfer && $claim->status !== ClaimState::REJECTED->value) {
                $metadata = ['transfer_uuid' => $commitment->transferUuid, 'original_digest' => $sameTransfer->transfer_digest, 'conflicting_digest' => $digest];
                $this->quarantinePreviouslySpentConflict($entry, $commitment, $digest);
                if ($claim->status === ClaimState::REDEEMED->value) {
                    // Late fraud evidence is preserved as an alert; completed ledger entries are never rewritten here.
                    $this->appendEvent($claim, ClaimState::REDEEMED->value, ClaimState::REDEEMED->value, 'late_conflicting_evidence', 'user', (int) $claim->user_id, $metadata);
                    $this->audit->log((int) $claim->user_id, 'exapay.offline.reconciliation.late_conflict', request(), ['claim_uuid' => $claim->claim_uuid] + $metadata + ['ledger_reversal' => false]);
                } else {
                    $this->transition($claim, ClaimState::CONFLICT_DETECTED, 'conflict_detected', 'conflicting_transfer_digest', 'user', (int) $claim->user_id, $metadata);
                }
            }
        }
    }

    private function quarantinePreviouslySpentConflict(array $entry, OfflineTransferCommitment $commitment, string $digest): void
    {
        $prior = ExaPayOfflineTransferSession::query()->where('claimed_transfer_uuid', $commitment->transferUuid)
            ->where('transfer_digest', '<>', $digest)->first();
        if (!$prior) return;
        $input = ExaPayOfflineValueOutput::query()->where('output_uuid', $commitment->inputOutputUuid)->first();
        $alreadySpent = \App\Models\ExaPayOfflineOutputSpend::query()->where('output_uuid', $commitment->inputOutputUuid)->exists();
        if (!$input || (!$alreadySpent && $input->status === 'available')) return;

        $recipientDevice = ExaPayOfflineDevice::query()->where('device_uuid', strtolower($commitment->recipientDeviceUuid))->first();
        $recipient = $recipientDevice ? User::query()->find($recipientDevice->user_id) : null;
        if (!$recipient) return;
        try {
            // This invokes normal signature checks and the existing lineage quarantine. It never posts ledger entries.
            $this->transfers->reconcile($recipient, [$entry]);
        } catch (OfflinePaymentException) {
            // The claim event/evidence remain durable; invalid or incomplete late evidence cannot alter the ledger.
        }
    }

    /** Returns ordered evidence, null for an incomplete path, or false for a conflicting path. */
    private function orderedPath(ExaPayOfflineReconciliationClaim $claim, array $entries): array|false|null
    {
        $target = strtolower((string) $claim->expected_output_uuid);
        $existingTarget = ExaPayOfflineValueOutput::query()->where('output_uuid', $target)->first();
        if ($existingTarget) {
            if ($entries !== []) return false;
            if ((int) $existingTarget->owner_user_id !== (int) $claim->user_id || (int) $existingTarget->amount_minor !== (int) $claim->expected_amount_minor || $existingTarget->status !== 'available') return false;
            return [];
        }
        if ($entries === []) return null;

        $producers = [];
        foreach ($entries as $entry) {
            $producer = (string) $entry['recipient_output_uuid'];
            if (isset($producers[$producer])) return false;
            $producers[$producer] = $entry;
            if (!empty($entry['change_output_uuid'])) {
                $change = (string) $entry['change_output_uuid'];
                if (isset($producers[$change])) return false;
                $producers[$change] = $entry;
            }
        }
        if (!isset($producers[$target]) || (string) $producers[$target]['recipient_output_uuid'] !== $target) return false;

        $reverse = [];
        $cursor = $target;
        $visited = [];
        while (true) {
            $existing = ExaPayOfflineValueOutput::query()->where('output_uuid', $cursor)->first();
            if ($existing) break;
            if (isset($visited[$cursor])) return false;
            $visited[$cursor] = true;
            $entry = $producers[$cursor] ?? null;
            if (!$entry) return null;
            $reverse[] = $entry;
            $cursor = strtolower((string) $entry['input_output_uuid']);
        }
        if (count($reverse) !== count($entries)) return false;
        $base = ExaPayOfflineValueOutput::query()->where('output_uuid', $cursor)->first();
        if (!$base || !in_array($base->status, ['available', 'spent', 'quarantined'], true)) return null;
        return array_reverse($reverse);
    }

    private function evidenceEntries(ExaPayOfflineReconciliationClaim $claim): array
    {
        return $claim->evidence()->whereIn('status', ['SUBMITTED', 'VERIFIED'])->orderBy('id')->get()->map(function (ExaPayOfflineReconciliationEvidence $row): array {
            return (array) $row->canonical_payload + [
                'sender_signature' => (string) $row->sender_signature,
                'recipient_signature' => (string) $row->recipient_signature,
            ];
        })->all();
    }

    private function commitment(array $entry): OfflineTransferCommitment
    {
        try {
            return new OfflineTransferCommitment(
                (string) $entry['transfer_uuid'], (string) $entry['sender_device_uuid'], (string) $entry['recipient_device_uuid'],
                (int) $entry['sender_key_version'], (int) $entry['recipient_key_version'], (string) $entry['input_output_uuid'],
                (string) $entry['recipient_output_uuid'], isset($entry['change_output_uuid']) ? (string) $entry['change_output_uuid'] : null,
                (int) $entry['input_amount_minor'], (int) $entry['transfer_amount_minor'], (int) $entry['change_amount_minor'],
                (int) $entry['transfer_counter'], (int) $entry['protocol_version'], (int) $entry['hop_count'], (string) $entry['nonce'],
                (int) $entry['signed_at'], (string) $entry['parent_digest'], (string) $entry['idempotency_key'], (string) ($entry['asset'] ?? 'SIMUSDT'),
            );
        } catch (Throwable) {
            throw new OfflinePaymentException('reconciliation_evidence_invalid', 'A transfer commitment is malformed or internally inconsistent.', 422);
        }
    }

    public function assertSignedPolicy(ExaPayOfflineReconciliationClaim $claim): void
    {
        $policy = ExaPayOfflineRiskPolicy::query()->whereKey(1)->lockForUpdate()->first();
        if (!$policy) throw new OfflinePaymentException('risk_policy_unavailable', 'The signed simulation risk policy is unavailable.', 503);
        try {
            $contract = new OfflineRiskPolicyContract(
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
        } catch (Throwable) {
            throw new OfflinePaymentException('risk_policy_signature_invalid', 'The offline risk policy is malformed.', 503);
        }
        $canonical = $contract->canonicalPayload();
        if (!$policy->enabled || (int) $claim->expected_amount_minor > (int) $policy->max_amount_minor
            || !$policy->policy_signature || !$policy->policy_public_key || !$policy->policy_digest || !$policy->policy_expires_at
            || $policy->policy_expires_at->isPast() || !hash_equals((string) $policy->policy_digest, hash('sha256', $canonical))
            || !$this->crypto->verifyIssuerPayload((string) $policy->policy_public_key, $canonical, (string) $policy->policy_signature)) {
            throw new OfflinePaymentException('risk_policy_signature_invalid', 'The offline risk policy is disabled, expired, unsigned, or inconsistent.', 503);
        }
        if (app(OfflineDeviceTrustService::class)->latestPolicyVersion() !== (int) $policy->policy_version) {
            throw new OfflinePaymentException('risk_policy_downgrade_detected', 'The offline policy version is stale or has been rolled back.', 503);
        }
    }

    private function failClaim(ExaPayOfflineReconciliationClaim $claim, ClaimState $state, string $reason, string $message): array
    {
        $claim->reason_code = $reason;
        $claim->result = ['asset' => 'SIMUSDT', 'message' => $message, 'online_credit' => false];
        $claim->save();
        $this->transition($claim, $state, $reason, $reason, null, null, ['online_credit' => false]);
        $this->audit->log((int) $claim->user_id, 'exapay.offline.reconciliation.' . strtolower($state->value), request(), [
            'claim_uuid' => $claim->claim_uuid, 'reason_code' => $reason, 'simulation_only' => true,
        ]);

        return $this->response($claim->fresh());
    }

    public function transition(ExaPayOfflineReconciliationClaim $claim, ClaimState $next, string $event, ?string $reason, ?string $actorType, ?int $actorId, array $metadata): void
    {
        $from = ClaimState::from((string) $claim->status);
        if ($from !== $next && !$from->mayTransitionTo($next)) {
            throw new \LogicException("Invalid offline reconciliation transition {$from->value} -> {$next->value}.");
        }
        if ($from !== $next) {
            $claim->status = $next->value;
            $claim->reason_code = $reason;
            $claim->save();
        }
        $this->appendEvent($claim, $from->value, $next->value, $event, $actorType, $actorId, $metadata);
    }

    private function appendEvent(ExaPayOfflineReconciliationClaim $claim, ?string $from, string $to, string $event, ?string $actorType, ?int $actorId, array $metadata): void
    {
        $last = $claim->events()->latest('sequence')->first();
        $sequence = ((int) ($last?->sequence ?? 0)) + 1;
        $previous = $last?->event_digest;
        $digest = $this->eventDigest($claim, $sequence, $from, $to, $event, $actorType, $actorId, $metadata, $previous);
        ExaPayOfflineReconciliationEvent::query()->create([
            'claim_id' => $claim->id, 'sequence' => $sequence, 'from_state' => $from,
            'to_state' => $to, 'event_type' => $event, 'previous_digest' => $previous,
            'actor_type' => $actorType, 'actor_id' => $actorId,
            'event_digest' => $digest,
            'metadata' => $metadata, 'created_at' => now(),
        ]);
    }

    private function response(ExaPayOfflineReconciliationClaim $claim): array
    {
        $events = $claim->events()->orderBy('sequence')->get();
        $previous = null;
        $eventChainValid = true;
        $invalidEventSequence = null;
        foreach ($events as $event) {
            $expected = $this->eventDigest($claim, (int) $event->sequence, $event->from_state, $event->to_state,
                $event->event_type, $event->actor_type, $event->actor_id ? (int) $event->actor_id : null,
                (array) $event->metadata, $previous);
            if (($event->previous_digest !== $previous || !hash_equals((string) $event->event_digest, $expected)) && $invalidEventSequence === null) {
                $eventChainValid = false;
                $invalidEventSequence = (int) $event->sequence;
            }
            $previous = (string) $event->event_digest;
        }

        return [
            'claim_uuid' => $claim->claim_uuid, 'status' => $claim->status,
            'expected_output_uuid' => $claim->expected_output_uuid, 'expected_amount_minor' => (int) $claim->expected_amount_minor,
            'evidence_count' => $claim->evidence()->count(), 'reason_code' => $claim->reason_code,
            'result' => $claim->result, 'verified_at' => $claim->verified_at?->toIso8601String(),
            'redeemed_at' => $claim->redeemed_at?->toIso8601String(), 'simulation_only' => true,
            'real_funds' => false, 'audit_chain_valid' => $eventChainValid,
            'audit_chain_invalid_sequence' => $invalidEventSequence,
            'events' => $events->map(fn ($event): array => [
                'sequence' => (int) $event->sequence, 'from_state' => $event->from_state,
                'to_state' => $event->to_state, 'event_type' => $event->event_type,
                'event_digest' => $event->event_digest, 'created_at' => $event->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function eventDigest(ExaPayOfflineReconciliationClaim $claim, int $sequence, ?string $from, string $to, string $event, ?string $actorType, ?int $actorId, array $metadata, ?string $previous): string
    {
        $metadata = $this->canonicalize($metadata);
        $body = json_encode([
            'claim_uuid' => $claim->claim_uuid, 'sequence' => $sequence, 'from_state' => $from,
            'to_state' => $to, 'event_type' => $event, 'actor_type' => $actorType,
            'actor_id' => $actorId, 'metadata' => $metadata,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return hash('sha256', ($previous ?? '') . '|' . $body);
    }

    private function canonicalize(array $value): array
    {
        $isList = array_is_list($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) $value[$key] = $this->canonicalize($item);
        }
        if (!$isList) ksort($value, SORT_STRING);
        return $value;
    }

    private function assertSimulation(): void
    {
        if (!(bool) config('exapay_offline.enabled') || !OfflineEnvironmentGuard::allowsSimulation()) {
            throw new OfflinePaymentException('offline_simulation_disabled', 'Offline reconciliation is unavailable in this environment.', 404);
        }
    }
}
