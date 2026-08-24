<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FiatDepositProcessingService
{
    public function __construct(private readonly SettlementService $settlement)
    {
    }

    public function detectFromWebhook(array $webhookEvent): array
    {
        $normalized = json_decode((string) ($webhookEvent['normalized_payload'] ?? '{}'), true) ?: [];
        $provider = (string) $webhookEvent['provider'];
        $providerTx = (string) ($normalized['provider_transaction_id'] ?? '');
        if ($providerTx === '') {
            throw new RuntimeException('Provider transaction id is required.');
        }

        return DB::transaction(function () use ($normalized, $provider, $providerTx): array {
            $existing = DB::table('fiat_deposits')->where('provider', $provider)->where('provider_transaction_id', $providerTx)->lockForUpdate()->first();
            if ($existing) {
                return (array) $existing;
            }

            $virtual = DB::table('phase10_virtual_accounts')
                ->where('provider', $provider)
                ->where('account_number', (string) ($normalized['destination_account'] ?? ''))
                ->where('status', 'ACTIVE')
                ->first();

            $gross = FinancialDecimal::normalize((string) ($normalized['amount'] ?? '0'));
            if (FinancialDecimal::compare($gross, '0') <= 0) {
                throw new RuntimeException('Fiat deposit amount must be greater than zero.');
            }
            $fee = FinancialDecimal::normalize((string) config('fiat.fees.deposit_flat', '0'));
            $net = FinancialDecimal::sub($gross, $fee);
            $status = $virtual ? 'DETECTED' : 'MANUAL_REVIEW';

            $pk = DB::table('fiat_deposits')->insertGetId([
                'deposit_id' => (string) Str::uuid(),
                'user_id' => $virtual?->user_id,
                'virtual_account_id' => $virtual?->id,
                'provider' => $provider,
                'provider_transaction_id' => $providerTx,
                'provider_reference' => (string) ($normalized['provider_reference'] ?? ''),
                'currency' => strtoupper((string) $normalized['currency']),
                'gross_amount' => $gross,
                'fee_amount' => $fee,
                'net_amount' => $net,
                'sender_name' => $normalized['sender_name'] ?? null,
                'sender_bank' => $normalized['sender_bank'] ?? null,
                'destination_account' => $normalized['destination_account'] ?? null,
                'status' => $status,
                'settlement_status' => 'PENDING',
                'detected_at' => now(),
                'metadata' => json_encode(['source' => 'payment_webhook'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($pk, 'FIAT_DEPOSIT_DETECTED', null, ['status' => $status]);

            return (array) DB::table('fiat_deposits')->where('id', $pk)->first();
        });
    }

    public function verify(string $depositId): array
    {
        return DB::transaction(function () use ($depositId): array {
            $deposit = $this->lock($depositId);
            if ($deposit->status === 'CREDITED') {
                return (array) $deposit;
            }
            if ($deposit->status !== 'DETECTED') {
                throw new RuntimeException('Fiat deposit cannot be verified in current state.');
            }

            DB::table('fiat_deposits')->where('id', $deposit->id)->update([
                'status' => 'VERIFIED',
                'settlement_status' => 'VERIFIED',
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('fiat_deposits')->where('id', $deposit->id)->first();
        });
    }

    public function credit(string $depositId): array
    {
        return DB::transaction(function () use ($depositId): array {
            $deposit = $this->lock($depositId);
            if ($deposit->status === 'CREDITED') {
                return (array) $deposit;
            }
            if ($deposit->status !== 'VERIFIED') {
                throw new RuntimeException('Fiat deposit is not verified.');
            }
            if (!$deposit->user_id) {
                throw new RuntimeException('Fiat deposit has no user assignment.');
            }

            $reference = 'fiat-deposit:'.$deposit->provider.':'.$deposit->provider_transaction_id;
            $this->settlement->fiatDepositCredit((int) $deposit->user_id, (string) $deposit->currency, (string) $deposit->gross_amount, (string) $deposit->fee_amount, $reference, [
                'provider' => $deposit->provider,
                'provider_transaction_id' => $deposit->provider_transaction_id,
                'deposit_id' => $deposit->deposit_id,
            ]);
            DB::table('fiat_deposits')->where('id', $deposit->id)->update([
                'status' => 'CREDITED',
                'settlement_status' => 'SETTLED',
                'ledger_reference' => $reference,
                'credited_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event((int) $deposit->id, 'FIAT_DEPOSIT_CREDITED', (array) $deposit, ['status' => 'CREDITED', 'ledger_reference' => $reference]);

            return (array) DB::table('fiat_deposits')->where('id', $deposit->id)->first();
        });
    }

    private function lock(string $depositId): object
    {
        $query = DB::table('fiat_deposits')->where('deposit_id', $depositId);
        if (is_numeric($depositId)) {
            $query->orWhere('id', $depositId);
        }
        $deposit = $query->lockForUpdate()->first();
        if (!$deposit) {
            throw new RuntimeException('Fiat deposit not found.');
        }

        return $deposit;
    }

    private function event(int $depositPk, string $type, ?array $before, array $after): void
    {
        DB::table('fiat_deposit_events')->insert([
            'fiat_deposit_id' => $depositPk,
            'event_type' => $type,
            'before_state' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_state' => json_encode($after, JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['source' => 'phase10_fiat'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
