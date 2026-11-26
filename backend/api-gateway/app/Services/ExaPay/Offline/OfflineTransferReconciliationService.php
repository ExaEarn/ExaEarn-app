<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflinePaymentException;
use App\Domain\ExaPay\Offline\OfflineRiskPolicyContract;
use App\Domain\ExaPay\Offline\OfflineTransferCommitment;
use App\Models\ExaPayOfflineDevice;
use App\Models\ExaPayOfflineDeviceKeyHistory;
use App\Models\ExaPayOfflineOutputSpend;
use App\Models\ExaPayOfflineTransferSession;
use App\Models\ExaPayOfflineValueOutput;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Accepts recipient-submitted signed evidence; it never redeems or credits online funds. */
final class OfflineTransferReconciliationService
{
    public function __construct(private readonly OfflineInstrumentCrypto $crypto, private readonly AuditLogService $audit)
    {
    }

    /** @param list<array<string, mixed>> $chain */
    public function reconcile(User $recipient, array $chain): array
    {
        $this->assertEnabled();
        if ($chain === [] || count($chain) > 100) {
            throw new OfflinePaymentException('transfer_chain_size_invalid', 'Submit between one and one hundred signed transfers.', 422);
        }

        return DB::transaction(function () use ($recipient, $chain): array {
            User::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            $policy = ExaPayOfflineRiskPolicy::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if (!$policy->enabled) throw new OfflinePaymentException('offline_simulation_disabled', 'Offline simulation is currently disabled.', 403);
            if (strlen(json_encode($chain, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) > (int) $policy->max_journal_bytes) {
                throw new OfflinePaymentException('journal_capacity_exceeded', 'The submitted signed journal exceeds the active byte limit.', 422);
            }
            $policyContract = new OfflineRiskPolicyContract(
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
            $policyCanonical = $policyContract->canonicalPayload();
            if (!$policy->policy_signature || !$policy->policy_public_key || !$policy->policy_digest || !$policy->policy_expires_at
                || $policy->policy_expires_at->isPast() || !hash_equals((string) $policy->policy_digest, hash('sha256', $policyCanonical))
                || !$this->crypto->verifyIssuerPayload((string) $policy->policy_public_key, $policyCanonical, (string) $policy->policy_signature)) {
                throw new OfflinePaymentException('risk_policy_signature_invalid', 'The offline risk policy is unsigned, expired, or invalid.', 503);
            }
            if (app(OfflineDeviceTrustService::class)->latestPolicyVersion() !== (int) $policy->policy_version) {
                throw new OfflinePaymentException('risk_policy_downgrade_detected', 'The offline policy version is stale or has been rolled back.', 503);
            }

            $results = [];
            $cumulative = 0;
            foreach ($chain as $index => $entry) {
                $commitment = $this->commitment($entry);
                $cumulative += $commitment->transferAmountMinor;
                if ($cumulative > (int) $policy->max_cumulative_spending_minor) {
                    throw new OfflinePaymentException('journal_spending_limit_exceeded', 'The signed transfer chain exceeds the cumulative spending policy.', 422);
                }
                if (!in_array($commitment->asset, (array) $policy->supported_assets, true)
                    || count($chain) > (int) $policy->max_transfers_per_journal || $commitment->transferAmountMinor > (int) $policy->max_amount_minor
                    || $commitment->hopCount > (int) $policy->max_hops || $commitment->protocolVersion < (int) $policy->minimum_protocol_version) {
                    throw new OfflinePaymentException('transfer_risk_limit_exceeded', 'The transfer exceeds the active simulation risk policy.', 422);
                }
                $results[] = $this->recordOne($recipient, $entry, $commitment, $index === count($chain) - 1, $policy);
                if (($results[count($results) - 1]['status'] ?? null) === 'quarantined') {
                    $this->audit->log((int) $recipient->id, 'exapay.offline.transfer.double_spend_quarantined', request(), [
                        'transfer_uuid' => $results[count($results) - 1]['transfer_uuid'], 'simulation_only' => true,
                    ]);
                    return ['status' => 'conflict_quarantined', 'asset' => 'SIMUSDT', 'transfers' => $results,
                        'redemption_status' => 'not_redeemed', 'online_credit' => false];
                }
            }

            $this->audit->log((int) $recipient->id, 'exapay.offline.transfer.chain_reconciled', request(), [
                'transfer_count' => count($results), 'cumulative_amount_minor' => $cumulative,
                'final_device_uuid' => $results[count($results) - 1]['recipient_device_uuid'], 'simulation_only' => true,
                'online_credit' => false, 'redemption' => false,
            ]);

            return ['status' => 'evidence_recorded', 'asset' => 'SIMUSDT', 'transfers' => $results, 'redemption_status' => 'not_redeemed', 'online_credit' => false];
        }, 3);
    }

    /** Verify the complete issuer-to-leaf signature chain before a claim can be credited. */
    public function verifyOutputLineage(ExaPayOfflineValueOutput $output): void
    {
        $current = $output;
        $seen = [];
        for ($depth = 0; $depth <= 100; $depth++) {
            if (isset($seen[$current->output_uuid])) {
                throw new OfflinePaymentException('transfer_lineage_cycle', 'The stored output lineage contains a cycle.', 409);
            }
            $seen[$current->output_uuid] = true;
            $instrument = \App\Models\ExaPayOfflineInstrument::query()->whereKey($current->source_instrument_id)->first();
            if (!$instrument) throw new OfflinePaymentException('transfer_issuer_lineage_invalid', 'The output has no issuer-backed source instrument.', 409);
            $this->assertOutputCryptographicLineage($current, $instrument);
            if ($current->parent_output_uuid === null) return;
            $current = ExaPayOfflineValueOutput::query()->where('output_uuid', $current->parent_output_uuid)->first();
            if (!$current) throw new OfflinePaymentException('transfer_parent_lineage_invalid', 'The output parent is missing.', 409);
        }
        throw new OfflinePaymentException('transfer_lineage_depth_exceeded', 'The offline value lineage exceeds the supported hop limit.', 422);
    }

    private function recordOne(User $authenticatedRecipient, array $entry, OfflineTransferCommitment $commitment, bool $isFinal, ExaPayOfflineRiskPolicy $policy): array
    {
        $sender = ExaPayOfflineDevice::query()->where('device_uuid', strtolower($commitment->senderDeviceUuid))->lockForUpdate()->first();
        $receiver = ExaPayOfflineDevice::query()->where('device_uuid', strtolower($commitment->recipientDeviceUuid))->lockForUpdate()->first();
        if (!$sender || !$receiver || $sender->status !== 'active' || $receiver->status !== 'active' || $sender->id === $receiver->id) {
            throw new OfflinePaymentException('transfer_device_invalid', 'Both transfer devices must be enrolled, active, and distinct.', 403);
        }
        app(OfflineDeviceTrustService::class)->assertTransferAllowed($sender, $receiver, $commitment, $policy);
        User::query()->whereKey($sender->user_id)->lockForUpdate()->firstOrFail();
        if ($isFinal && (int) $receiver->user_id !== (int) $authenticatedRecipient->id) {
            throw new OfflinePaymentException('transfer_recipient_mismatch', 'Only the final enrolled recipient may submit this signed chain.', 403);
        }

        $canonical = $commitment->canonicalPayload();
        $senderSignature = (string) ($entry['sender_signature'] ?? '');
        $recipientSignature = (string) ($entry['recipient_signature'] ?? '');
        $senderKey = $this->keyForSignedAt($sender, $commitment->senderKeyVersion, $commitment->signedAt);
        $recipientKey = $this->keyForSignedAt($receiver, $commitment->recipientKeyVersion, $commitment->signedAt);
        if (!$senderKey || !$recipientKey) throw new OfflinePaymentException('transfer_key_version_invalid', 'A transfer uses an invalid or out-of-period device key version.', 403);
        if (!$this->crypto->verifyDeviceProof($senderKey, $canonical, $senderSignature)) {
            throw new OfflinePaymentException('transfer_sender_signature_invalid', 'The sender commitment signature is invalid.', 403);
        }
        if (!$this->crypto->verifyDeviceProof($recipientKey, $commitment->recipientChallenge($senderSignature), $recipientSignature)) {
            throw new OfflinePaymentException('transfer_recipient_proof_invalid', 'The recipient did not authenticate acceptance of this commitment.', 403);
        }
        // A disconnected payment may be reconciled long after it was signed. Bound
        // future clock skew here; bind the historic timestamp to the source
        // instrument's validity window below instead of the short online proof TTL.
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $commitment->nonce)
            || $commitment->signedAt > now()->timestamp + (int) config('exapay_offline.proof_max_age_seconds', 300)) {
            throw new OfflinePaymentException('transfer_proof_time_invalid', 'The signed transfer timestamp or nonce is invalid.', 422);
        }

        $digest = $commitment->digest();
        $existing = ExaPayOfflineTransferSession::query()->where('sender_device_id', $sender->id)->where('idempotency_key', $commitment->idempotencyKey)->lockForUpdate()->first();
        $idempotencyConflict = false;
        if ($existing) {
            if (hash_equals((string) $existing->transfer_digest, $digest)) return $this->transferResponse($existing, true);
            $idempotencyConflict = true;
        }

        $input = ExaPayOfflineValueOutput::query()->where('output_uuid', $commitment->inputOutputUuid)->lockForUpdate()->first();
        if (!$input || (int) $input->owner_device_id !== (int) $sender->id || (int) $input->owner_user_id !== (int) $sender->user_id
            || $input->asset !== 'SIMUSDT' || !hash_equals((string) $input->lineage_digest, $commitment->parentDigest)
            || (int) $input->amount_minor !== $commitment->inputAmountMinor || (int) $input->hop_count + 1 !== $commitment->hopCount) {
            throw new OfflinePaymentException('transfer_lineage_invalid', 'The input value, ownership, amount, hop, or parent lineage cannot be verified.', 409);
        }
        $sourceInstrument = \App\Models\ExaPayOfflineInstrument::query()->whereKey($input->source_instrument_id)->first();
        if (!$sourceInstrument || $sourceInstrument->expires_at->isPast() || $sourceInstrument->delivery_status !== 'acknowledged'
            || in_array($sourceInstrument->status, ['failed', 'redeemed', 'suspended'], true)) {
            throw new OfflinePaymentException('transfer_source_not_spendable', 'The signed source instrument is expired, unacknowledged, failed, redeemed, or suspended.', 409);
        }
        if ($commitment->signedAt < $sourceInstrument->issued_at->timestamp || $commitment->signedAt > $sourceInstrument->expires_at->timestamp) {
            throw new OfflinePaymentException('transfer_outside_instrument_validity', 'The signed transfer falls outside its source instrument validity period.', 422);
        }
        $this->assertOutputCryptographicLineage($input, $sourceInstrument);
        $sameTransfer = ExaPayOfflineTransferSession::query()->where(function ($query) use ($commitment): void {
            $query->where('transfer_uuid', $commitment->transferUuid)->orWhere('claimed_transfer_uuid', $commitment->transferUuid);
        })->lockForUpdate()->first();
        if ($sameTransfer && hash_equals((string) $sameTransfer->transfer_digest, $digest)) {
            return $this->transferResponse($sameTransfer, true);
        }

        $spend = ExaPayOfflineOutputSpend::query()->where('output_uuid', $input->output_uuid)->lockForUpdate()->first();
        if ($spend || $input->status !== 'available') {
            $input->status = 'quarantined';
            $input->version = (int) $input->version + 1;
            $input->save();
            if ($spend) {
                $spend->status = 'quarantined';
                $spend->save();
                ExaPayOfflineTransferSession::query()->whereKey($spend->transfer_session_id)->update(['status' => 'quarantined', 'updated_at' => now()]);
                $this->quarantineDescendants([$input->output_uuid]);
            }
            $quarantined = $this->persistQuarantinedClaim($sender, $receiver, $commitment, $digest, $canonical, $senderSignature, $recipientSignature);
            return $this->transferResponse($quarantined, false);
        }

        if ($idempotencyConflict || $sameTransfer) {
            throw new OfflinePaymentException('transfer_idempotency_conflict', 'This transfer identifier was already used for a different commitment.', 409);
        }
        if ((int) $sender->device_counter + 1 !== $commitment->transferCounter) {
            throw new OfflinePaymentException('transfer_counter_conflict', 'The sender transfer counter is not the next expected value.', 409);
        }

        if (ExaPayOfflineValueOutput::query()->whereIn('output_uuid', array_filter([$commitment->recipientOutputUuid, $commitment->changeOutputUuid]))->exists()) {
            throw new OfflinePaymentException('transfer_output_uuid_conflict', 'A transfer output identifier has already been used.', 409);
        }

        $recipientOutstanding = (int) ExaPayOfflineValueOutput::query()->where('owner_user_id', $receiver->user_id)->where('asset', 'SIMUSDT')
            ->whereIn('status', ['available', 'locked', 'quarantined'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('exapay_offline_output_spends as s')
                    ->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid');
            })->sum('amount_minor');
        if ((int) $receiver->user_id === (int) $sender->user_id) $recipientOutstanding -= (int) $input->amount_minor;
        $recipientExposureLimit = min((int) $policy->max_outstanding_per_user_minor, (int) $policy->max_incoming_exposure_per_user_minor);
        if ($commitment->transferAmountMinor > $recipientExposureLimit - $recipientOutstanding) {
            throw new OfflinePaymentException('recipient_offline_limit_exceeded', 'The recipient offline balance would exceed the signed policy limit.', 422);
        }
        $deviceOutstanding = (int) ExaPayOfflineValueOutput::query()->where('owner_device_id', $receiver->id)->where('asset', 'SIMUSDT')
            ->whereIn('status', ['available', 'locked', 'quarantined'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('exapay_offline_output_spends as s')->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid'))
            ->sum('amount_minor');
        if ($commitment->transferAmountMinor > (int) $policy->max_device_exposure_minor - $deviceOutstanding) {
            throw new OfflinePaymentException('device_incoming_exposure_limit_exceeded', 'The receiving device would exceed its signed offline exposure limit.', 422);
        }

        if (DB::table('exapay_offline_device_nonces')->where('nonce_digest', hash('sha256', $commitment->nonce))->exists()) {
            throw new OfflinePaymentException('transfer_nonce_replayed', 'This transfer nonce has already been used.', 409);
        }
        if ((int) $sender->transaction_count >= (int) $policy->max_transactions_per_device
            || $commitment->transferAmountMinor > (int) $sender->offline_spending_allowance_minor - (int) $sender->cumulative_spent_minor
            || $commitment->transferAmountMinor > (int) $policy->max_cumulative_spending_minor - (int) $sender->cumulative_spent_minor) {
            app(OfflineDeviceTrustService::class)->recordFraud('DEVICE_SPENDING_LIMIT_EXCEEDED', 'HIGH', (int) $sender->user_id, (int) $sender->id, [
                'transfer_uuid' => $commitment->transferUuid, 'requested_minor' => $commitment->transferAmountMinor,
                'cumulative_spent_minor' => (int) $sender->cumulative_spent_minor,
            ]);
            throw new OfflinePaymentException('device_spending_limit_exceeded', 'The signed device spending allowance or transaction limit is exhausted.', 422);
        }

        $session = ExaPayOfflineTransferSession::query()->create([
            'transfer_uuid' => $commitment->transferUuid, 'transfer_digest' => $digest,
            'claimed_transfer_uuid' => $commitment->transferUuid,
            'sender_device_id' => $sender->id, 'recipient_device_id' => $receiver->id,
            'sender_key_version' => $commitment->senderKeyVersion, 'recipient_key_version' => $commitment->recipientKeyVersion,
            'input_output_uuid' => $input->output_uuid, 'recipient_output_uuid' => $commitment->recipientOutputUuid,
            'change_output_uuid' => $commitment->changeOutputUuid, 'idempotency_key' => $commitment->idempotencyKey,
            'input_amount_minor' => $commitment->inputAmountMinor, 'transfer_amount_minor' => $commitment->transferAmountMinor,
            'change_amount_minor' => $commitment->changeAmountMinor, 'transfer_counter' => $commitment->transferCounter,
            'protocol_version' => $commitment->protocolVersion, 'hop_count' => $commitment->hopCount,
            'nonce_digest' => hash('sha256', $commitment->nonce), 'parent_digest' => $commitment->parentDigest,
            'canonical_payload' => $commitment->canonicalArray(), 'sender_signature' => $senderSignature,
            'recipient_signature' => $recipientSignature, 'status' => 'acknowledged',
            'prepared_at' => now()->setTimestamp($commitment->signedAt), 'committed_at' => now(), 'acknowledged_at' => now(),
        ]);
        ExaPayOfflineOutputSpend::query()->create(['output_uuid' => $input->output_uuid, 'transfer_session_id' => $session->id, 'status' => 'committed', 'spent_at' => now()]);
        $input->status = 'spent';
        $input->version = (int) $input->version + 1;
        $input->save();

        $this->createOutput($commitment->recipientOutputUuid, (int) $receiver->user_id, (int) $receiver->id, $input, $commitment, $digest, $commitment->transferAmountMinor, 'recipient');
        if ($commitment->changeAmountMinor > 0 && $commitment->changeOutputUuid !== null) {
            $this->createOutput($commitment->changeOutputUuid, (int) $sender->user_id, (int) $sender->id, $input, $commitment, $digest, $commitment->changeAmountMinor, 'change');
        }

        $nonceInserted = DB::table('exapay_offline_device_nonces')->insertOrIgnore([
            'user_id' => $sender->user_id, 'device_uuid' => $sender->device_uuid,
            'nonce_digest' => hash('sha256', $commitment->nonce), 'purpose' => 'transfer',
            'signed_at' => now()->setTimestamp($commitment->signedAt), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($nonceInserted !== 1) throw new OfflinePaymentException('transfer_nonce_replayed', 'This transfer nonce has already been used.', 409);
        $sender->device_counter = $commitment->transferCounter;
        $sender->cumulative_spent_minor = (int) $sender->cumulative_spent_minor + $commitment->transferAmountMinor;
        $sender->transaction_count = (int) $sender->transaction_count + 1;
        $sender->last_counter_accepted_at = now();
        $sender->last_seen_at = now();
        $sender->save();
        $receiver->last_seen_at = now();
        $receiver->save();

        return $this->transferResponse($session, false);
    }

    private function createOutput(string $uuid, int $userId, int $deviceId, ExaPayOfflineValueOutput $input, OfflineTransferCommitment $commitment, string $digest, int $amount, string $kind): void
    {
        ExaPayOfflineValueOutput::query()->create([
            'output_uuid' => $uuid, 'source_instrument_id' => $input->source_instrument_id,
            'owner_user_id' => $userId, 'owner_device_id' => $deviceId,
            'parent_output_uuid' => $input->output_uuid, 'origin_transfer_uuid' => $commitment->transferUuid,
            'asset' => 'SIMUSDT', 'amount_minor' => $amount, 'hop_count' => $commitment->hopCount,
            'lineage_digest' => hash('sha256', $digest . '|' . $kind . '|' . $uuid), 'status' => 'available', 'version' => 0,
        ]);
    }

    private function persistQuarantinedClaim(ExaPayOfflineDevice $sender, ExaPayOfflineDevice $receiver, OfflineTransferCommitment $commitment, string $digest, string $canonical, string $senderSignature, string $recipientSignature): ExaPayOfflineTransferSession
    {
        $recordUuid = ExaPayOfflineTransferSession::query()->where('transfer_uuid', $commitment->transferUuid)->exists()
            ? (string) Str::uuid() : $commitment->transferUuid;
        $session = ExaPayOfflineTransferSession::query()->create([
            'transfer_uuid' => $recordUuid,
            'claimed_transfer_uuid' => $commitment->transferUuid,
            'transfer_digest' => $digest, 'sender_device_id' => $sender->id, 'recipient_device_id' => $receiver->id,
            'sender_key_version' => $commitment->senderKeyVersion, 'recipient_key_version' => $commitment->recipientKeyVersion,
            'input_output_uuid' => $commitment->inputOutputUuid, 'recipient_output_uuid' => $commitment->recipientOutputUuid,
            'change_output_uuid' => $commitment->changeOutputUuid, 'idempotency_key' => $commitment->idempotencyKey,
            'input_amount_minor' => $commitment->inputAmountMinor, 'transfer_amount_minor' => $commitment->transferAmountMinor,
            'change_amount_minor' => $commitment->changeAmountMinor, 'transfer_counter' => $commitment->transferCounter,
            'protocol_version' => $commitment->protocolVersion, 'hop_count' => $commitment->hopCount,
            'nonce_digest' => hash('sha256', $commitment->nonce), 'parent_digest' => $commitment->parentDigest,
            'canonical_payload' => json_decode($canonical, true, 512, JSON_THROW_ON_ERROR),
            'sender_signature' => $senderSignature, 'recipient_signature' => $recipientSignature,
            'status' => 'quarantined', 'prepared_at' => now()->setTimestamp($commitment->signedAt), 'committed_at' => now(),
        ]);
        DB::table('exapay_offline_device_nonces')->insertOrIgnore([
            'user_id' => $sender->user_id, 'device_uuid' => $sender->device_uuid,
            'nonce_digest' => hash('sha256', $commitment->nonce), 'purpose' => 'transfer_conflict',
            'signed_at' => now()->setTimestamp($commitment->signedAt), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $session;
    }

    /** Quarantine every descendant value so a cloned branch cannot continue spending it. */
    private function quarantineDescendants(array $outputUuids): void
    {
        $visited = [];
        while ($outputUuids !== []) {
            $frontier = array_values(array_diff(array_unique($outputUuids), array_keys($visited)));
            if ($frontier === []) break;
            foreach ($frontier as $uuid) $visited[$uuid] = true;
            $children = ExaPayOfflineValueOutput::query()->whereIn('parent_output_uuid', $frontier)->lockForUpdate()->get();
            foreach ($children as $child) {
                $child->status = 'quarantined';
                $child->version = (int) $child->version + 1;
                $child->save();
                $spend = ExaPayOfflineOutputSpend::query()->where('output_uuid', $child->output_uuid)->first();
                if ($spend) {
                    $spend->status = 'quarantined';
                    $spend->save();
                    ExaPayOfflineTransferSession::query()->whereKey($spend->transfer_session_id)->update(['status' => 'quarantined', 'updated_at' => now()]);
                }
            }
            $outputUuids = $children->pluck('output_uuid')->all();
        }
    }

    private function commitment(array $entry): OfflineTransferCommitment
    {
        try {
            return new OfflineTransferCommitment(
                (string) $entry['transfer_uuid'], (string) $entry['sender_device_uuid'], (string) $entry['recipient_device_uuid'],
                (int) $entry['sender_key_version'], (int) $entry['recipient_key_version'],
                (string) $entry['input_output_uuid'], (string) $entry['recipient_output_uuid'], isset($entry['change_output_uuid']) ? (string) $entry['change_output_uuid'] : null,
                (int) $entry['input_amount_minor'], (int) $entry['transfer_amount_minor'], (int) $entry['change_amount_minor'],
                (int) $entry['transfer_counter'], (int) $entry['protocol_version'], (int) $entry['hop_count'],
                (string) $entry['nonce'], (int) $entry['signed_at'], (string) $entry['parent_digest'],
                (string) $entry['idempotency_key'], (string) ($entry['asset'] ?? 'SIMUSDT'),
            );
        } catch (\Throwable $exception) {
            throw new OfflinePaymentException('transfer_commitment_invalid', 'The offline transfer commitment is malformed.', 422);
        }
    }

    private function keyForSignedAt(ExaPayOfflineDevice $device, int $version, int $signedAt): ?string
    {
        $history = ExaPayOfflineDeviceKeyHistory::query()->where('device_id', $device->id)->where('key_version', $version)->first();
        // Second-resolution timestamps use a fail-closed boundary at revocation.
        if (!$history || $history->valid_from->timestamp > $signedAt || ($history->revoked_at && $history->revoked_at->timestamp <= $signedAt)) return null;
        return (string) $history->public_key;
    }

    private function assertOutputCryptographicLineage(ExaPayOfflineValueOutput $output, \App\Models\ExaPayOfflineInstrument $instrument): void
    {
        $rootOutput = ExaPayOfflineValueOutput::query()->where('output_uuid', $instrument->instrument_uuid)->first();
        $payload = (array) $instrument->payload;
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $expectedRootDigest = hash('sha256', 'exaearn-offline-root:v1|' . $instrument->instrument_uuid . '|' . hash('sha256', $canonical));
        $issuerKeyId = (string) ($payload['issuer_key_id'] ?? substr(hash('sha256', (string) $instrument->issuer_public_key), 0, 32));
        if (\App\Models\ExaPayOfflineIssuerKey::query()->where('key_id', $issuerKeyId)->where('status', 'COMPROMISED')->exists()) {
            throw new OfflinePaymentException('issuer_key_compromised', 'The source instrument was signed by a compromised issuer key.', 409);
        }
        if ($instrument->asset !== 'SIMUSDT' || $instrument->delivery_status !== 'acknowledged'
            || $instrument->expires_at->isPast() || in_array($instrument->status, ['failed', 'redeemed', 'suspended'], true)
            || !$rootOutput || (int) $rootOutput->source_instrument_id !== (int) $instrument->id || (int) $rootOutput->owner_user_id !== (int) $instrument->user_id
            || (int) $rootOutput->owner_device_id !== (int) $instrument->device_id || (int) $rootOutput->amount_minor !== (int) $instrument->amount_minor
            || !hash_equals((string) $rootOutput->lineage_digest, $expectedRootDigest)
            || !$this->crypto->verifyIssuerPayload((string) $instrument->issuer_public_key, $canonical, (string) $instrument->issuer_signature)) {
            throw new OfflinePaymentException('transfer_issuer_lineage_invalid', 'The root issuance signature or reserve lineage cannot be verified.', 409);
        }
        if ($output->output_uuid === $instrument->instrument_uuid) {
            if ($output->parent_output_uuid !== null || (int) $output->source_instrument_id !== (int) $instrument->id) {
                throw new OfflinePaymentException('transfer_root_output_invalid', 'The root output does not match its signed issuance.', 409);
            }
            return;
        }

        $origin = ExaPayOfflineTransferSession::query()->where(function ($query) use ($output): void {
            $query->where('transfer_uuid', $output->origin_transfer_uuid)->orWhere('claimed_transfer_uuid', $output->origin_transfer_uuid);
        })->where('status', 'acknowledged')->first();
        if (!$origin || $output->parent_output_uuid !== $origin->input_output_uuid || (int) $output->source_instrument_id !== (int) $instrument->id) {
            throw new OfflinePaymentException('transfer_parent_lineage_invalid', 'The offline output has no acknowledged signed parent transfer.', 409);
        }
        $originData = (array) $origin->canonical_payload;
        $originCommitment = $this->commitment($originData);
        $originCanonical = $originCommitment->canonicalPayload();
        $originSender = ExaPayOfflineDevice::query()->find($origin->sender_device_id);
        $originRecipient = ExaPayOfflineDevice::query()->find($origin->recipient_device_id);
        $originSenderKey = $originSender ? $this->keyForSignedAt($originSender, $originCommitment->senderKeyVersion, $originCommitment->signedAt) : null;
        $originRecipientKey = $originRecipient ? $this->keyForSignedAt($originRecipient, $originCommitment->recipientKeyVersion, $originCommitment->signedAt) : null;
        $parent = ExaPayOfflineValueOutput::query()->where('output_uuid', $originCommitment->inputOutputUuid)->first();
        $isRecipientOutput = $output->output_uuid === $originCommitment->recipientOutputUuid
            && (int) $output->owner_device_id === (int) $origin->recipient_device_id
            && (int) $output->amount_minor === $originCommitment->transferAmountMinor;
        $isChangeOutput = $originCommitment->changeOutputUuid !== null && $output->output_uuid === $originCommitment->changeOutputUuid
            && (int) $output->owner_device_id === (int) $origin->sender_device_id
            && (int) $output->amount_minor === $originCommitment->changeAmountMinor;
        $kind = $isRecipientOutput ? 'recipient' : ($isChangeOutput ? 'change' : 'invalid');
        $expectedDigest = hash('sha256', $originCommitment->digest() . '|' . $kind . '|' . $output->output_uuid);
        $expectedOwnerUserId = $isRecipientOutput ? $originRecipient?->user_id : $originSender?->user_id;
        if ($originCommitment->signedAt < $instrument->issued_at->timestamp || $originCommitment->signedAt > $instrument->expires_at->timestamp
            || !$originSenderKey || !$originRecipientKey || !$parent
            || (int) $parent->owner_device_id !== (int) $origin->sender_device_id
            || (int) $parent->owner_user_id !== (int) $originSender?->user_id
            || (int) $parent->amount_minor !== $originCommitment->inputAmountMinor
            || (int) $parent->hop_count + 1 !== $originCommitment->hopCount
            || (int) $output->owner_user_id !== (int) $expectedOwnerUserId
            || $parent->lineage_digest !== $originCommitment->parentDigest
            || !hash_equals((string) $origin->transfer_digest, $originCommitment->digest())
            || !$this->crypto->verifyDeviceProof($originSenderKey, $originCanonical, (string) $origin->sender_signature)
            || !$this->crypto->verifyDeviceProof($originRecipientKey, $originCommitment->recipientChallenge((string) $origin->sender_signature), (string) $origin->recipient_signature)
            || $kind === 'invalid' || !hash_equals((string) $output->lineage_digest, $expectedDigest)) {
            throw new OfflinePaymentException('transfer_parent_signature_invalid', 'The output parent transfer signature, ownership, or digest is invalid.', 409);
        }
    }

    private function transferResponse(ExaPayOfflineTransferSession $session, bool $replay): array
    {
        return ['transfer_uuid' => $session->claimed_transfer_uuid ?: $session->transfer_uuid, 'transfer_digest' => $session->transfer_digest, 'status' => $session->status,
            'recipient_device_uuid' => ExaPayOfflineDevice::query()->find($session->recipient_device_id)?->device_uuid,
            'idempotent_replay' => $replay, 'real_funds' => false, 'redemption_status' => 'not_redeemed'];
    }

    private function assertEnabled(): void
    {
        if (!(bool) config('exapay_offline.enabled') || !OfflineEnvironmentGuard::allowsSimulation()) {
            throw new OfflinePaymentException('offline_simulation_disabled', 'Offline transfer reconciliation is unavailable in this environment.', 404);
        }
    }
}
