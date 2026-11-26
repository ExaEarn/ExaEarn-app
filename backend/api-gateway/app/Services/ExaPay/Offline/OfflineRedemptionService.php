<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflinePaymentException;
use App\Domain\ExaPay\Offline\OfflineReconciliationClaimState as ClaimState;
use App\Models\Account;
use App\Models\ExaPayOfflineInstrument;
use App\Models\ExaPayOfflineRedemption;
use App\Models\ExaPayOfflineReconciliationClaim;
use App\Models\ExaPayOfflineReserve;
use App\Models\ExaPayOfflineValueOutput;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Atomic SIMUSDT-only redemption. The output, reserve counters, claim and double-entry ledger commit together. */
final class OfflineRedemptionService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly OfflineReconciliationClaimService $claims,
        private readonly OfflineTransferReconciliationService $transfers,
        private readonly AuditLogService $audit,
    ) {
    }

    public function redeem(User $user, string $claimUuid, string $idempotencyKey): array
    {
        $this->assertEnabled();
        app(OfflineDeviceTrustService::class)->assertRedemptionAllowed();
        if (!preg_match('/^[A-Za-z0-9._:-]{16,160}$/', $idempotencyKey)) {
            throw new OfflinePaymentException('idempotency_key_required', 'A valid redemption idempotency key is required.', 422);
        }

        return DB::transaction(function () use ($user, $claimUuid, $idempotencyKey): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $claim = ExaPayOfflineReconciliationClaim::query()->where('claim_uuid', $claimUuid)->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$claim) throw new OfflinePaymentException('reconciliation_claim_not_found', 'The verified claim was not found for this account.', 404);
            $output = ExaPayOfflineValueOutput::query()->where('output_uuid', $claim->expected_output_uuid)->lockForUpdate()->first();
            if (!$output) throw new OfflinePaymentException('offline_output_not_found', 'The verified offline output no longer exists.', 409);

            $requestDigest = hash('sha256', json_encode([
                'claim_uuid' => $claim->claim_uuid, 'user_id' => (int) $user->id,
                'output_uuid' => $output->output_uuid, 'amount_minor' => (int) $output->amount_minor,
                'asset' => 'SIMUSDT',
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $existing = ExaPayOfflineRedemption::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals((string) $existing->request_digest, $requestDigest)) {
                    throw new OfflinePaymentException('redemption_idempotency_conflict', 'This redemption key was already used for a different request.', 409);
                }
                return $this->response($existing, true);
            }
            $existingOutput = ExaPayOfflineRedemption::query()->where('output_uuid', $output->output_uuid)->lockForUpdate()->first();
            if ($existingOutput) {
                throw new OfflinePaymentException('offline_output_already_redeemed', 'This output has already been consumed by a redemption.', 409);
            }
            if ($claim->status !== ClaimState::VERIFIED->value || (int) $claim->expected_amount_minor !== (int) $output->amount_minor) {
                throw new OfflinePaymentException('reconciliation_claim_not_verified', 'Only a verified claim for this exact output amount can be redeemed.', 409);
            }
            $this->claims->assertSignedPolicy($claim);
            if ((int) $output->owner_user_id !== (int) $user->id || $output->asset !== 'SIMUSDT' || $output->status !== 'available') {
                throw new OfflinePaymentException('offline_output_not_redeemable', 'The offline output is not available to this account.', 409);
            }
            if (\App\Models\ExaPayOfflineOutputSpend::query()->where('output_uuid', $output->output_uuid)->lockForUpdate()->exists()
                || ExaPayOfflineValueOutput::query()->where('parent_output_uuid', $output->output_uuid)->lockForUpdate()->exists()) {
                throw new OfflinePaymentException('offline_output_lineage_conflict', 'Only an unspent leaf output can be redeemed.', 409);
            }

            $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            $reserveAccount = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            $this->ledger->getOrCreateAccount((int) $user->id, 'funding', 'SIMUSDT');
            $funding = Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            $amountMinor = (int) $output->amount_minor;
            if (!$reserve || !$reserveAccount || !$funding || $amountMinor < 1
                || (int) $reserve->reserved_minor !== (int) $reserve->outstanding_liability_minor
                || (int) $reserve->issued_minor !== (int) $reserve->outstanding_liability_minor + (int) $reserve->redeemed_minor + (int) $reserve->extinguished_minor
                || $amountMinor > (int) $reserve->outstanding_liability_minor
                || FinancialDecimal::compare((string) $reserveAccount->balance, $this->minorToDecimal((int) $reserve->reserved_minor)) !== 0) {
                throw new OfflinePaymentException('offline_reserve_reconciliation_required', 'The simulation reserve, liability, or issuance accounting requires reconciliation.', 503);
            }

            $this->transfers->verifyOutputLineage($output);

            $this->claims->transition($claim, ClaimState::REDEMPTION_PENDING, 'redemption_started', null, 'user', (int) $user->id, [
                'output_uuid' => $output->output_uuid, 'amount_minor' => $amountMinor,
            ]);
            $ledgerReference = 'EXAPAY-OFFLINE-REDEEM-' . strtoupper(substr(hash('sha256', $user->id . '|' . $idempotencyKey), 0, 40));
            $redemption = ExaPayOfflineRedemption::query()->create([
                'redemption_uuid' => (string) Str::uuid(), 'claim_id' => $claim->id,
                'output_uuid' => $output->output_uuid, 'user_id' => $user->id,
                'asset' => 'SIMUSDT', 'amount_minor' => $amountMinor,
                'idempotency_key' => $idempotencyKey, 'request_digest' => $requestDigest,
                'ledger_reference' => $ledgerReference, 'status' => ClaimState::REDEMPTION_PENDING->value,
            ]);

            $decimal = $this->minorToDecimal($amountMinor);
            $ledgerTransaction = $this->ledger->postDoubleEntry($ledgerReference, 'ExaPay simulated offline value redemption', [
                ['account_id' => $reserveAccount->id, 'amount' => FinancialDecimal::sub('0', $decimal), 'asset' => 'SIMUSDT', 'metadata' => ['offline_redemption' => $redemption->redemption_uuid]],
                ['account_id' => $funding->id, 'amount' => $decimal, 'asset' => 'SIMUSDT', 'user_id' => (int) $user->id, 'metadata' => ['offline_redemption' => $redemption->redemption_uuid]],
            ], 'exapay_offline_redemption', [
                'source_service' => 'exapay_offline_simulation', 'initiated_by_type' => 'user', 'initiated_by_id' => (int) $user->id,
                'payment_mode' => 'OFFLINE_STORED_VALUE', 'claim_uuid' => $claim->claim_uuid,
                'output_uuid' => $output->output_uuid, 'amount_minor' => $amountMinor,
                'real_funds' => false, 'exactly_once_key' => $idempotencyKey,
            ]);
            $this->assertLedgerPosting($ledgerTransaction, $ledgerReference, $reserveAccount->id, $funding->id, $decimal);

            $output->status = 'redeemed';
            $output->version = (int) $output->version + 1;
            $output->save();
            $reserve->reserved_minor = (int) $reserve->reserved_minor - $amountMinor;
            $reserve->outstanding_liability_minor = (int) $reserve->outstanding_liability_minor - $amountMinor;
            $reserve->redeemed_minor = (int) $reserve->redeemed_minor + $amountMinor;
            $reserve->version = (int) $reserve->version + 1;
            $reserve->save();
            $redemption->ledger_transaction_id = $ledgerTransaction->id;
            $redemption->status = ClaimState::REDEEMED->value;
            $redemption->redeemed_at = now();
            $redemption->save();
            $this->claims->transition($claim, ClaimState::REDEEMED, 'redemption_completed', null, 'system', null, [
                'redemption_uuid' => $redemption->redemption_uuid, 'ledger_reference' => $ledgerReference,
                'amount_minor' => $amountMinor, 'real_funds' => false,
            ]);
            $claim->redeemed_at = now();
            $claim->result = array_merge($claim->result ?? [], [
                'redemption_uuid' => $redemption->redemption_uuid, 'ledger_reference' => $ledgerReference,
                'online_credit' => true, 'real_funds' => false,
            ]);
            $claim->save();

            $instrument = ExaPayOfflineInstrument::query()->whereKey($output->source_instrument_id)->lockForUpdate()->firstOrFail();
            $remainingForInstrument = ExaPayOfflineValueOutput::query()->where('source_instrument_id', $instrument->id)
                ->whereIn('status', ['available', 'locked', 'quarantined'])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('exapay_offline_output_spends as s')->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid');
                })->sum('amount_minor');
            if ((int) $remainingForInstrument === 0 && $instrument->status !== 'redeemed') {
                $instrument->status = 'redeemed';
                $instrument->consumed_at = now();
                $instrument->save();
            }
            $this->audit->log((int) $user->id, 'exapay.offline.redemption.completed', request(), [
                'claim_uuid' => $claim->claim_uuid, 'redemption_uuid' => $redemption->redemption_uuid,
                'output_uuid' => $output->output_uuid, 'amount_minor' => $amountMinor,
                'ledger_reference' => $ledgerReference, 'real_funds' => false,
            ]);

            return $this->response($redemption, false);
        }, 3);
    }

    public function history(User $user, int $perPage = 25): array
    {
        $this->assertEnabled();
        return ExaPayOfflineRedemption::query()->where('user_id', $user->id)->latest('id')->paginate(min(100, max(1, $perPage)))->toArray();
    }

    public function show(User $user, string $redemptionUuid): array
    {
        $this->assertEnabled();
        $redemption = ExaPayOfflineRedemption::query()->where('user_id', $user->id)->where('redemption_uuid', $redemptionUuid)->first();
        if (!$redemption) throw new OfflinePaymentException('redemption_not_found', 'The redemption record was not found for this account.', 404);
        return $this->response($redemption, true);
    }

    public function adminList(?string $status = null, int $perPage = 50): array
    {
        $query = ExaPayOfflineRedemption::query()->latest('id');
        if ($status !== null) $query->where('status', $status);
        return $query->paginate(min(100, max(1, $perPage)))->toArray();
    }

    /**
     * Reconciles a persisted pending row against the authoritative ledger. It never reposts credit.
     * If the ledger and reserve cannot be proven consistent, the row is quarantined for manual review.
     */
    public function recover(string $redemptionUuid): array
    {
        $this->assertNonProductionEnvironment();

        return DB::transaction(function () use ($redemptionUuid): array {
            $redemption = ExaPayOfflineRedemption::query()->where('redemption_uuid', $redemptionUuid)->lockForUpdate()->first();
            if (!$redemption) throw new OfflinePaymentException('redemption_not_found', 'The redemption record was not found.', 404);
            if ($redemption->status === ClaimState::REDEEMED->value) return $this->response($redemption, true);
            if (!in_array($redemption->status, [ClaimState::REDEMPTION_PENDING->value, ClaimState::RECOVERY_REQUIRED->value], true)) {
                throw new OfflinePaymentException('redemption_not_recoverable', 'This redemption is not pending recovery.', 409);
            }

            $output = ExaPayOfflineValueOutput::query()->where('output_uuid', $redemption->output_uuid)->lockForUpdate()->first();
            $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            $reserveAccount = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            $funding = Account::query()->where('user_id', $redemption->user_id)->where('account_type', 'funding')->where('asset', 'SIMUSDT')->lockForUpdate()->first();
            $transaction = LedgerTransaction::query()->where('reference', $redemption->ledger_reference)->lockForUpdate()->first();
            $consistent = $output && $reserve && $reserveAccount && $funding && $transaction
                && $transaction->status === 'completed'
                && $this->ledgerPostingMatches($transaction, $reserveAccount->id, $funding->id, $this->minorToDecimal((int) $redemption->amount_minor));

            if (!$consistent) return $this->markRecoveryRequired($redemption, 'ledger_posting_unverifiable');
            if ($output->status === 'available'
                && (int) $reserve->reserved_minor === (int) $reserve->outstanding_liability_minor
                && (int) $reserve->issued_minor === (int) $reserve->outstanding_liability_minor + (int) $reserve->redeemed_minor + (int) $reserve->extinguished_minor
                && (int) $reserve->outstanding_liability_minor >= (int) $redemption->amount_minor
                && FinancialDecimal::compare((string) $reserveAccount->balance, $this->minorToDecimal((int) $reserve->reserved_minor - (int) $redemption->amount_minor)) === 0) {
                // Ledger already moved the exact value. Complete only the offline evidence/accounting state.
                $reserve->reserved_minor = (int) $reserve->reserved_minor - (int) $redemption->amount_minor;
                $reserve->outstanding_liability_minor = (int) $reserve->outstanding_liability_minor - (int) $redemption->amount_minor;
                $reserve->redeemed_minor = (int) $reserve->redeemed_minor + (int) $redemption->amount_minor;
                $reserve->version = (int) $reserve->version + 1;
                $reserve->save();
                $output->status = 'redeemed';
                $output->version = (int) $output->version + 1;
                $output->save();
            } elseif ($output->status !== 'redeemed'
                || (int) $reserve->reserved_minor !== (int) $reserve->outstanding_liability_minor
                || (int) $reserve->issued_minor !== (int) $reserve->outstanding_liability_minor + (int) $reserve->redeemed_minor + (int) $reserve->extinguished_minor
                || FinancialDecimal::compare((string) $reserveAccount->balance, $this->minorToDecimal((int) $reserve->reserved_minor)) !== 0) {
                return $this->markRecoveryRequired($redemption, 'reserve_output_state_mismatch');
            }

            $redemption->status = ClaimState::REDEEMED->value;
            $redemption->ledger_transaction_id = $transaction->id;
            $redemption->redeemed_at ??= now();
            $redemption->save();
            $claim = ExaPayOfflineReconciliationClaim::query()->whereKey($redemption->claim_id)->lockForUpdate()->firstOrFail();
            if ($claim->status !== ClaimState::REDEEMED->value) {
                $this->claims->transition($claim, ClaimState::REDEEMED, 'redemption_recovered', null, 'admin', null, ['redemption_uuid' => $redemption->redemption_uuid]);
                $claim->redeemed_at = now();
                $claim->result = array_merge($claim->result ?? [], ['redemption_uuid' => $redemption->redemption_uuid, 'online_credit' => true, 'real_funds' => false]);
                $claim->save();
            }
            $instrument = ExaPayOfflineInstrument::query()->whereKey($output->source_instrument_id)->lockForUpdate()->first();
            if ($instrument) {
                $remaining = ExaPayOfflineValueOutput::query()->where('source_instrument_id', $instrument->id)
                    ->whereIn('status', ['available', 'locked', 'quarantined'])
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')->from('exapay_offline_output_spends as s')->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid');
                    })->sum('amount_minor');
                if ((int) $remaining === 0) {
                    $instrument->status = 'redeemed';
                    $instrument->consumed_at ??= now();
                    $instrument->save();
                }
            }
            $this->audit->log((int) $redemption->user_id, 'exapay.offline.redemption.recovered', request(), [
                'redemption_uuid' => $redemption->redemption_uuid, 'ledger_reference' => $redemption->ledger_reference,
                'amount_minor' => (int) $redemption->amount_minor, 'ledger_reposted' => false, 'real_funds' => false,
            ]);
            return $this->response($redemption, true);
        }, 3);
    }

    private function assertLedgerPosting(LedgerTransaction $transaction, string $reference, int $reserveAccountId, int $fundingAccountId, string $amount): void
    {
        if ($transaction->reference !== $reference || $transaction->status !== 'completed' || $transaction->transaction_type !== 'exapay_offline_redemption') {
            throw new OfflinePaymentException('offline_ledger_posting_invalid', 'The redemption ledger transaction was not completed as expected.', 503);
        }
        if (!$this->ledgerPostingMatches($transaction, $reserveAccountId, $fundingAccountId, $amount)) {
            throw new OfflinePaymentException('offline_ledger_posting_conflict', 'The existing ledger reference does not match this exact redemption.', 409);
        }
    }

    private function ledgerPostingMatches(LedgerTransaction $transaction, int $reserveAccountId, int $fundingAccountId, string $amount): bool
    {
        if ($transaction->transaction_type !== 'exapay_offline_redemption') return false;
        $entries = LedgerEntry::query()->where('reference', $transaction->reference)->get();
        $reserveEntry = $entries->firstWhere('account_id', $reserveAccountId);
        $fundingEntry = $entries->firstWhere('account_id', $fundingAccountId);
        return $entries->count() === 2 && $reserveEntry && $fundingEntry
            && FinancialDecimal::compare((string) $reserveEntry->amount, FinancialDecimal::sub('0', $amount)) === 0
            && FinancialDecimal::compare((string) $fundingEntry->amount, $amount) === 0;
    }

    private function markRecoveryRequired(ExaPayOfflineRedemption $redemption, string $reason): array
    {
        $redemption->status = ClaimState::RECOVERY_REQUIRED->value;
        $redemption->save();
        $claim = ExaPayOfflineReconciliationClaim::query()->whereKey($redemption->claim_id)->lockForUpdate()->first();
        if ($claim && $claim->status === ClaimState::REDEMPTION_PENDING->value) {
            $this->claims->transition($claim, ClaimState::RECOVERY_REQUIRED, 'redemption_recovery_required', $reason, 'system', null, ['redemption_uuid' => $redemption->redemption_uuid]);
        }
        $this->audit->log((int) $redemption->user_id, 'exapay.offline.redemption.recovery_required', request(), [
            'redemption_uuid' => $redemption->redemption_uuid, 'reason_code' => $reason,
            'automatic_credit_retry' => false, 'real_funds' => false,
        ]);
        return $this->response($redemption->fresh(), false);
    }

    private function response(ExaPayOfflineRedemption $redemption, bool $replay): array
    {
        return [
            'redemption_uuid' => $redemption->redemption_uuid, 'claim_uuid' => $redemption->claim?->claim_uuid,
            'output_uuid' => $redemption->output_uuid, 'asset' => $redemption->asset,
            'amount_minor' => (int) $redemption->amount_minor, 'status' => $redemption->status,
            'ledger_reference' => $redemption->ledger_reference, 'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
            'idempotent_replay' => $replay, 'real_funds' => false,
        ];
    }

    private function minorToDecimal(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function assertEnabled(): void
    {
        if (!(bool) config('exapay_offline.enabled') || !(bool) config('exapay_offline.redemption_enabled')
            || !OfflineEnvironmentGuard::allowsSimulation()) {
            throw new OfflinePaymentException('offline_redemption_disabled', 'SIMUSDT redemption is unavailable in this environment.', 404);
        }
    }

    private function assertNonProductionEnvironment(): void
    {
        if (!OfflineEnvironmentGuard::allowsSimulation()) {
            throw new OfflinePaymentException('offline_redemption_disabled', 'Offline redemption recovery is unavailable in this environment.', 404);
        }
    }
}
