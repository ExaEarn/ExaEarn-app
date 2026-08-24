<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\ReservationService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CustodyWithdrawalService
{
    public function __construct(
        private readonly CustodyRegistryService $registry,
        private readonly BlockchainProviderManager $providers,
        private readonly DevelopmentSigningProvider $signer,
        private readonly WithdrawalFeeService $fees,
        private readonly WithdrawalRiskEngine $risk,
        private readonly LedgerService $ledger,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlement,
        private readonly BlockchainNonceService $nonces,
        private readonly BitcoinUtxoService $utxos,
    ) {
    }

    public function request(User $user, array $payload, string $idempotencyKey): array
    {
        $asset = strtoupper((string) ($payload['asset'] ?? ''));
        $network = strtolower((string) ($payload['network'] ?? ''));
        $amount = FinancialDecimal::normalize((string) ($payload['amount'] ?? '0'));
        $destination = trim((string) ($payload['destination_address'] ?? ''));
        $memoTag = $payload['memo_tag'] ?? null;

        $networkConfig = $this->registry->network($network);
        $assetConfig = $this->registry->asset($asset, $network);
        if (!(bool) $networkConfig['withdrawal_enabled'] || !(bool) $assetConfig['withdrawal_enabled']) {
            throw new RuntimeException('Withdrawals are not enabled for this asset/network.');
        }
        if (FinancialDecimal::compare($amount, (string) $assetConfig['minimum_withdrawal']) < 0) {
            throw new RuntimeException('Withdrawal amount is below the minimum.');
        }
        if (FinancialDecimal::compare($amount, (string) $assetConfig['maximum_withdrawal']) > 0) {
            throw new RuntimeException('Withdrawal amount exceeds the maximum.');
        }

        $provider = $this->providers->providerFor($network);
        if (!$provider->validateAddress($network, $destination, $memoTag)) {
            throw new RuntimeException('Invalid destination address for the selected network.');
        }

        $quote = $this->fees->quote($asset, $network, $amount);
        $risk = $this->risk->evaluate($user, $asset, $network, $amount, $destination, $payload['context'] ?? []);

        return DB::transaction(function () use ($amount, $asset, $destination, $idempotencyKey, $memoTag, $network, $payload, $quote, $risk, $user): array {
            $existing = DB::table('custody_withdrawals')->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return (array) $existing;
            }
            if ($risk['decision'] === 'REJECT') {
                throw new RuntimeException('Withdrawal rejected by risk policy.');
            }

            $status = $risk['decision'] === 'REQUIRE_REVIEW' ? 'APPROVAL_REQUIRED' : 'VALIDATING';
            $withdrawalPk = DB::table('custody_withdrawals')->insertGetId([
                'withdrawal_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'asset' => $asset,
                'network' => $network,
                'amount' => $amount,
                'network_fee' => $quote['network_fee'],
                'platform_fee' => $quote['platform_fee'],
                'destination_address' => $destination,
                'memo_tag' => $memoTag,
                'status' => $status,
                'risk_decision' => $risk['decision'],
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
                'metadata' => json_encode(['risk_reasons' => $risk['reasons'], 'client_context_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($withdrawalPk, 'WITHDRAWAL_REQUESTED', null, ['status' => $status, 'risk_decision' => $risk['decision']]);

            if ($status !== 'APPROVAL_REQUIRED') {
                $this->reserve($withdrawalPk, $quote['total_debit']);
            }

            return (array) DB::table('custody_withdrawals')->where('id', $withdrawalPk)->first();
        });
    }

    public function approve(int|string $withdrawalId, ?int $adminId = null): array
    {
        return DB::transaction(function () use ($adminId, $withdrawalId): array {
            $withdrawal = $this->lock($withdrawalId);
            if (!in_array($withdrawal->status, ['APPROVAL_REQUIRED', 'RISK_REVIEW'], true)) {
                throw new RuntimeException('Withdrawal is not awaiting approval.');
            }
            $quote = $this->fees->quote((string) $withdrawal->asset, (string) $withdrawal->network, (string) $withdrawal->amount);
            $this->reserve((int) $withdrawal->id, $quote['total_debit']);
            DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'metadata' => json_encode(array_merge(json_decode((string) ($withdrawal->metadata ?? '{}'), true) ?: [], ['approved_by' => $adminId]), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $this->event((int) $withdrawal->id, 'WITHDRAWAL_APPROVED', (array) $withdrawal, ['status' => 'APPROVED', 'admin_id' => $adminId]);

            return (array) DB::table('custody_withdrawals')->where('id', $withdrawal->id)->first();
        });
    }

    public function buildSignAndBroadcast(int|string $withdrawalId): array
    {
        return DB::transaction(function () use ($withdrawalId): array {
            $withdrawal = $this->lock($withdrawalId);
            if (!in_array($withdrawal->status, ['BALANCE_RESERVED', 'APPROVED', 'QUEUED'], true)) {
                throw new RuntimeException('Withdrawal is not ready for blockchain construction.');
            }

            $wallet = DB::table('custody_wallets')
                ->where('network', $withdrawal->network)
                ->where('asset', $withdrawal->asset)
                ->where('classification', 'HOT')
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->first();

            if (!$wallet && !app()->environment(['local', 'testing'])) {
                DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update(['status' => 'LIQUIDITY_REBALANCE_REQUIRED', 'updated_at' => now()]);
                throw new RuntimeException('Hot wallet liquidity is not configured.');
            }

            $fromAddress = $wallet?->address ?: 'development-hot-wallet';
            $family = (string) config("custody.networks.{$withdrawal->network}.family");
            $nonce = $family === 'evm' ? $this->nonces->reserveNext((string) $withdrawal->network, $fromAddress) : null;
            $utxoSelection = $family === 'utxo' ? $this->utxos->selectAndReserve((string) $withdrawal->amount, (string) $withdrawal->withdrawal_id) : null;
            $unsigned = [
                'withdrawal_id' => $withdrawal->withdrawal_id,
                'network' => $withdrawal->network,
                'asset' => $withdrawal->asset,
                'from_address' => $fromAddress,
                'to_address' => $withdrawal->destination_address,
                'memo_tag' => $withdrawal->memo_tag,
                'amount' => (string) $withdrawal->amount,
                'network_fee' => (string) $withdrawal->network_fee,
                'nonce' => $nonce,
                'utxos' => $utxoSelection,
            ];

            DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update(['status' => 'SIGNING', 'updated_at' => now()]);
            $requestHash = hash('sha256', json_encode($unsigned, JSON_THROW_ON_ERROR));
            DB::table('custody_signing_requests')->updateOrInsert(
                ['request_hash' => $requestHash],
                [
                    'signing_request_id' => (string) Str::uuid(),
                    'custody_withdrawal_id' => $withdrawal->id,
                    'provider' => $this->signer->providerId(),
                    'network' => (string) $withdrawal->network,
                    'status' => 'REQUESTED',
                    'unsigned_payload' => json_encode($unsigned, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $signed = $this->signer->signTransaction((string) $withdrawal->network, $unsigned, ['withdrawal_id' => $withdrawal->withdrawal_id]);
            DB::table('custody_signing_requests')->where('request_hash', $requestHash)->update([
                'status' => 'SIGNED',
                'signed_payload_reference' => json_encode(['signed_hash' => hash('sha256', json_encode($signed, JSON_THROW_ON_ERROR))], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update(['status' => 'BROADCASTING', 'updated_at' => now()]);
            $provider = $this->providers->providerFor((string) $withdrawal->network);
            $broadcast = $provider->broadcastTransaction((string) $withdrawal->network, $signed);
            if (empty($broadcast['tx_hash'])) {
                throw new RuntimeException('Blockchain provider did not return a transaction hash.');
            }
            DB::table('custody_broadcast_attempts')->insert([
                'custody_withdrawal_id' => $withdrawal->id,
                'network' => (string) $withdrawal->network,
                'provider' => $provider->providerId(),
                'status' => 'BROADCASTED',
                'tx_hash' => (string) $broadcast['tx_hash'],
                'attempt' => 1,
                'metadata' => json_encode(['provider_status' => $broadcast['status'] ?? null], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update([
                'status' => 'BROADCASTED',
                'tx_hash' => (string) $broadcast['tx_hash'],
                'broadcasted_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event((int) $withdrawal->id, 'WITHDRAWAL_BROADCASTED', (array) $withdrawal, ['status' => 'BROADCASTED', 'tx_hash' => (string) $broadcast['tx_hash']]);

            return (array) DB::table('custody_withdrawals')->where('id', $withdrawal->id)->first();
        });
    }

    public function updateConfirmations(int|string $withdrawalId, int $confirmations): array
    {
        return DB::transaction(function () use ($confirmations, $withdrawalId): array {
            $withdrawal = $this->lock($withdrawalId);
            if (!$withdrawal->tx_hash) {
                throw new RuntimeException('Withdrawal has not been broadcast.');
            }
            $required = (int) config("custody.networks.{$withdrawal->network}.finality_confirmations", 1);
            DB::table('custody_transaction_confirmations')->updateOrInsert(
                ['network' => $withdrawal->network, 'tx_hash' => $withdrawal->tx_hash],
                [
                    'confirmations' => $confirmations,
                    'status' => $confirmations >= $required ? 'FINALIZED' : 'CONFIRMING',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update([
                'status' => $confirmations >= $required ? 'COMPLETED' : 'CONFIRMING',
                'completed_at' => $confirmations >= $required ? now() : null,
                'updated_at' => now(),
            ]);

            if ($confirmations >= $required && !$withdrawal->ledger_reference) {
                $reference = 'custody-withdrawal:' . $withdrawal->withdrawal_id;
                $this->settlement->custodyWithdrawal((string) $withdrawal->reservation_id, $reference, (string) $withdrawal->amount, (string) $withdrawal->network_fee, (string) $withdrawal->platform_fee, [
                    'source_service' => 'custody_withdrawal',
                    'tx_hash' => $withdrawal->tx_hash,
                    'network' => $withdrawal->network,
                ]);
                DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update(['ledger_reference' => $reference]);
            }

            return (array) DB::table('custody_withdrawals')->where('id', $withdrawal->id)->first();
        });
    }

    private function reserve(int $withdrawalPk, string $totalDebit): void
    {
        $withdrawal = DB::table('custody_withdrawals')->where('id', $withdrawalPk)->lockForUpdate()->first();
        if (!$withdrawal) {
            throw new RuntimeException('Withdrawal not found.');
        }
        if ($withdrawal->reservation_id) {
            return;
        }

        $account = $this->ledger->getOrCreateAccount((int) $withdrawal->user_id, 'funding', (string) $withdrawal->asset);
        $reservation = $this->reservations->reserve(
            $account->id,
            (string) $withdrawal->asset,
            $totalDebit,
            'custody_withdrawal',
            'custody_withdrawal',
            (string) $withdrawal->withdrawal_id,
            'custody-withdrawal:' . $withdrawal->withdrawal_id,
            ['withdrawal_id' => $withdrawal->withdrawal_id],
        );
        DB::table('custody_withdrawals')->where('id', $withdrawal->id)->update([
            'reservation_id' => $reservation->reservation_id,
            'status' => 'BALANCE_RESERVED',
            'updated_at' => now(),
        ]);
        $this->event((int) $withdrawal->id, 'WITHDRAWAL_RESERVED', (array) $withdrawal, ['status' => 'BALANCE_RESERVED', 'reservation_id' => $reservation->reservation_id]);
    }

    private function lock(int|string $withdrawalId): object
    {
        $query = DB::table('custody_withdrawals')->where('withdrawal_id', $withdrawalId);
        if (is_numeric($withdrawalId)) {
            $query->orWhere('id', $withdrawalId);
        }
        $withdrawal = $query->lockForUpdate()->first();
        if (!$withdrawal) {
            throw new RuntimeException('Withdrawal not found.');
        }

        return $withdrawal;
    }

    private function event(int $withdrawalPk, string $type, ?array $before, array $after): void
    {
        DB::table('custody_withdrawal_events')->insert([
            'custody_withdrawal_id' => $withdrawalPk,
            'event_type' => $type,
            'correlation_id' => $after['tx_hash'] ?? $after['reservation_id'] ?? null,
            'before_state' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_state' => json_encode($after, JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['source' => 'custody_withdrawal_service'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
