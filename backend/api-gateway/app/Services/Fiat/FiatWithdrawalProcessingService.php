<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\ReservationService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FiatWithdrawalProcessingService
{
    public function __construct(
        private readonly FiatTransactionLimitService $limits,
        private readonly FiatWithdrawalRiskEngine $risk,
        private readonly ReservationService $reservations,
        private readonly LedgerService $ledger,
        private readonly SettlementService $settlement,
        private readonly PaymentProviderRouter $providers,
        private readonly TransferRecipientService $recipients,
    ) {
    }

    public function quote(string $currency, string $amount, ?string $provider = null): array
    {
        $currency = strtoupper($currency);
        $providerService = $this->providers->provider($provider ?? 'sandbox');
        $fee = $providerService->getTransferFee($currency, $amount);
        $receives = FinancialDecimal::sub($amount, '0');

        return [
            'currency' => $currency,
            'amount' => FinancialDecimal::normalize($amount),
            'fee_amount' => $fee,
            'recipient_receives' => $receives,
            'total_debit' => FinancialDecimal::add($amount, $fee),
            'provider' => $providerService->key(),
            'estimated_arrival' => 'Instant to 24 hours depending on bank rail status',
        ];
    }

    public function create(User $user, int $bankAccountId, string $currency, string $amount, string $idempotencyKey, ?string $provider = null): array
    {
        $currency = strtoupper($currency);
        $quote = $this->quote($currency, $amount, $provider);

        return DB::transaction(function () use ($amount, $bankAccountId, $currency, $idempotencyKey, $provider, $quote, $user): array {
            $existing = DB::table('phase10_fiat_withdrawals')
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return (array) $existing;
            }

            $bank = DB::table('user_bank_accounts')
                ->where('id', $bankAccountId)
                ->where('user_id', $user->id)
                ->where('currency', $currency)
                ->where('status', 'ACTIVE')
                ->first();
            if (!$bank || $bank->verification_status !== 'VERIFIED') {
                throw new RuntimeException('A verified beneficiary is required.');
            }

            $this->limits->assertWithdrawalAllowed((int) $user->id, $currency, $amount);
            $risk = $this->risk->evaluate((int) $user->id, $currency, $amount, ['bank_account_id' => $bankAccountId]);
            $status = $risk['requires_manual_review'] ? 'UNDER_REVIEW' : 'RESERVED';
            $reservation = null;
            if (!$risk['requires_manual_review']) {
                $account = $this->ledger->getOrCreateAccount((int) $user->id, 'funding', $currency);
                $reservation = $this->reservations->reserve(
                    $account->id,
                    $currency,
                    (string) $quote['total_debit'],
                    'fiat_withdrawal',
                    'fiat_withdrawal',
                    $idempotencyKey,
                    'fiat-withdrawal:'.$user->id.':'.$idempotencyKey,
                    ['bank_account_id' => $bankAccountId],
                );
            }

            $pk = DB::table('phase10_fiat_withdrawals')->insertGetId([
                'withdrawal_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'user_bank_account_id' => $bankAccountId,
                'provider' => $provider ?? 'sandbox',
                'currency' => $currency,
                'amount' => FinancialDecimal::normalize($amount),
                'fee_amount' => $quote['fee_amount'],
                'recipient_receives' => $quote['recipient_receives'],
                'status' => $status,
                'risk_decision' => (string) $risk['decision'],
                'reservation_id' => $reservation?->reservation_id,
                'idempotency_key' => $idempotencyKey,
                'metadata' => json_encode(['risk' => $risk], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($pk, 'FIAT_WITHDRAWAL_CREATED', null, ['status' => $status, 'risk' => $risk['decision']]);

            return (array) DB::table('phase10_fiat_withdrawals')->where('id', $pk)->first();
        });
    }

    public function submit(string $withdrawalId): array
    {
        return DB::transaction(function () use ($withdrawalId): array {
            $withdrawal = $this->lock($withdrawalId);
            if (in_array($withdrawal->status, ['SUBMITTED', 'PROCESSING', 'COMPLETED'], true)) {
                return (array) $withdrawal;
            }
            if ($withdrawal->status !== 'RESERVED') {
                throw new RuntimeException('Fiat withdrawal is not ready for transfer submission.');
            }

            $bank = DB::table('user_bank_accounts')->where('id', $withdrawal->user_bank_account_id)->firstOrFail();
            $recipient = $this->recipients->getOrCreate((int) $bank->id, (string) $withdrawal->provider);
            $provider = $this->providers->provider((string) $withdrawal->provider);
            $transfer = $provider->initiateTransfer([
                'recipient_id' => $recipient['provider_recipient_id'],
                'amount' => (string) $withdrawal->amount,
                'currency' => (string) $withdrawal->currency,
                'idempotency_key' => 'withdrawal-transfer:'.$withdrawal->withdrawal_id,
                'bank_code' => $bank->bank_code,
                'account_number' => $bank->account_number,
            ]);

            DB::table('provider_transfers')->updateOrInsert(
                ['provider' => $withdrawal->provider, 'idempotency_key' => 'withdrawal-transfer:'.$withdrawal->withdrawal_id],
                [
                    'transfer_id' => (string) Str::uuid(),
                    'fiat_withdrawal_id' => $withdrawal->id,
                    'currency' => $withdrawal->currency,
                    'amount' => $withdrawal->amount,
                    'fee_amount' => $withdrawal->fee_amount,
                    'provider_reference' => $transfer['provider_reference'] ?? null,
                    'status' => $transfer['status'] ?? 'SUBMITTED',
                    'metadata' => json_encode($transfer, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $status = in_array(($transfer['status'] ?? 'SUBMITTED'), ['SUCCESSFUL', 'COMPLETED'], true) ? 'PROCESSING' : 'SUBMITTED';
            DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->update([
                'status' => $status,
                'provider_reference' => $transfer['provider_reference'] ?? null,
                'submitted_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event((int) $withdrawal->id, 'FIAT_WITHDRAWAL_SUBMITTED', (array) $withdrawal, ['status' => $status]);

            return (array) DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->first();
        });
    }

    public function refreshProviderStatus(string $withdrawalId): array
    {
        return DB::transaction(function () use ($withdrawalId): array {
            $withdrawal = $this->lock($withdrawalId);
            if (!$withdrawal->provider_reference) {
                throw new RuntimeException('Withdrawal does not have a provider reference.');
            }
            $status = $this->providers->provider((string) $withdrawal->provider)
                ->verifyTransfer((string) $withdrawal->provider_reference)['status'] ?? 'UNKNOWN';
            $mapped = match ($status) {
                'SUCCESSFUL', 'COMPLETED' => 'PROCESSING',
                'FAILED' => 'FAILED',
                default => 'UNKNOWN',
            };
            DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->update(['status' => $mapped, 'updated_at' => now()]);
            $this->event((int) $withdrawal->id, 'FIAT_WITHDRAWAL_PROVIDER_STATUS', (array) $withdrawal, ['status' => $mapped, 'provider_status' => $status]);

            return (array) DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->first();
        });
    }

    public function complete(string $withdrawalId): array
    {
        return DB::transaction(function () use ($withdrawalId): array {
            $withdrawal = $this->lock($withdrawalId);
            if ($withdrawal->status === 'COMPLETED') {
                return (array) $withdrawal;
            }
            if (!in_array($withdrawal->status, ['SUBMITTED', 'PROCESSING', 'UNKNOWN'], true)) {
                throw new RuntimeException('Fiat withdrawal cannot be completed in current state.');
            }
            $reference = 'fiat-withdrawal:'.$withdrawal->withdrawal_id;
            $this->settlement->fiatWithdrawalSettle((string) $withdrawal->reservation_id, $reference, (string) $withdrawal->amount, (string) $withdrawal->fee_amount, [
                'withdrawal_id' => $withdrawal->withdrawal_id,
                'provider' => $withdrawal->provider,
                'provider_reference' => $withdrawal->provider_reference,
            ]);
            DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->update([
                'status' => 'COMPLETED',
                'ledger_reference' => $reference,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event((int) $withdrawal->id, 'FIAT_WITHDRAWAL_COMPLETED', (array) $withdrawal, ['status' => 'COMPLETED']);

            return (array) DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->first();
        });
    }

    public function failAndRelease(string $withdrawalId, string $reason): array
    {
        return DB::transaction(function () use ($reason, $withdrawalId): array {
            $withdrawal = $this->lock($withdrawalId);
            if ($withdrawal->status === 'FAILED') {
                return (array) $withdrawal;
            }
            if ($withdrawal->reservation_id) {
                $this->reservations->release((string) $withdrawal->reservation_id, null, ['failure_reason' => $reason]);
            }
            DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->update([
                'status' => 'FAILED',
                'metadata' => json_encode(array_merge((array) json_decode((string) $withdrawal->metadata, true), ['failure_reason' => $reason]), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $this->event((int) $withdrawal->id, 'FIAT_WITHDRAWAL_FAILED', (array) $withdrawal, ['status' => 'FAILED', 'reason' => $reason]);

            return (array) DB::table('phase10_fiat_withdrawals')->where('id', $withdrawal->id)->first();
        });
    }

    private function lock(string $withdrawalId): object
    {
        $query = DB::table('phase10_fiat_withdrawals')->where('withdrawal_id', $withdrawalId);
        if (is_numeric($withdrawalId)) {
            $query->orWhere('id', $withdrawalId);
        }
        $withdrawal = $query->lockForUpdate()->first();
        if (!$withdrawal) {
            throw new RuntimeException('Fiat withdrawal not found.');
        }

        return $withdrawal;
    }

    private function event(int $withdrawalPk, string $type, ?array $before, array $after): void
    {
        DB::table('fiat_withdrawal_events')->insert([
            'fiat_withdrawal_id' => $withdrawalPk,
            'event_type' => $type,
            'before_state' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_state' => json_encode($after, JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['source' => 'phase10_fiat'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
