<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Services\FinancialDecimal;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DepositMonitoringService
{
    public function __construct(
        private readonly CustodyRegistryService $registry,
        private readonly SettlementService $settlement,
    ) {
    }

    public function detect(array $evidence): array
    {
        $network = strtolower((string) ($evidence['network'] ?? ''));
        $asset = strtoupper((string) ($evidence['asset'] ?? ''));
        $txHash = (string) ($evidence['tx_hash'] ?? '');
        $eventIdentifier = (string) ($evidence['event_identifier'] ?? ($evidence['event_index'] ?? '0'));
        $amount = FinancialDecimal::normalize((string) ($evidence['amount'] ?? '0'));

        if ($network === '' || $asset === '' || $txHash === '' || !preg_match('/^[a-fA-F0-9]{32,128}$/', $txHash)) {
            throw new RuntimeException('Valid blockchain evidence is required.');
        }
        if (FinancialDecimal::compare($amount, '0') <= 0) {
            throw new RuntimeException('Deposit amount must be greater than zero.');
        }

        $networkConfig = $this->registry->network($network);
        $assetConfig = $this->registry->asset($asset, $network);
        $memoTag = $evidence['memo_tag'] ?? null;
        if ((bool) $networkConfig['memo_required'] && ($memoTag === null || $memoTag === '')) {
            return $this->recordUnsupported($evidence, 'MISSING_MEMO_TAG');
        }

        return DB::transaction(function () use ($amount, $asset, $assetConfig, $eventIdentifier, $evidence, $memoTag, $network, $txHash): array {
            $existing = DB::table('custody_deposits')
                ->where('network', $network)
                ->where('tx_hash', $txHash)
                ->where('event_identifier', $eventIdentifier)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return (array) $existing;
            }

            $assignment = DB::table('custody_address_assignments')
                ->join('custody_addresses', 'custody_addresses.id', '=', 'custody_address_assignments.custody_address_id')
                ->where('custody_addresses.network', $network)
                ->where('custody_addresses.address', (string) $evidence['destination'])
                ->where('custody_addresses.memo_tag', $memoTag)
                ->where('custody_address_assignments.asset', $asset)
                ->where('custody_address_assignments.status', 'ACTIVE')
                ->select('custody_address_assignments.user_id', 'custody_addresses.id as address_pk')
                ->first();

            $status = $assignment ? 'DETECTED' : 'MANUAL_REVIEW';
            if (!$assignment) {
                $status = 'UNSUPPORTED_ASSET_DETECTED';
            } elseif (FinancialDecimal::compare($amount, (string) $assetConfig['minimum_deposit']) < 0) {
                $status = 'DUST';
            }

            $depositPk = DB::table('custody_deposits')->insertGetId([
                'deposit_id' => (string) Str::uuid(),
                'user_id' => $assignment?->user_id,
                'custody_address_id' => $assignment?->address_pk,
                'network' => $network,
                'asset' => $asset,
                'tx_hash' => $txHash,
                'event_identifier' => $eventIdentifier,
                'block_height' => $evidence['block_height'] ?? null,
                'block_hash' => $evidence['block_hash'] ?? null,
                'sender' => $evidence['sender'] ?? null,
                'destination' => (string) $evidence['destination'],
                'memo_tag' => $memoTag,
                'amount' => $amount,
                'confirmations' => (int) ($evidence['confirmations'] ?? 0),
                'required_confirmations' => (int) $assetConfig['required_confirmations'],
                'detection_source' => (string) ($evidence['detection_source'] ?? 'scanner'),
                'status' => $status,
                'detected_at' => now(),
                'metadata' => json_encode(['raw_evidence_hash' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR))], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->event($depositPk, 'DEPOSIT_DETECTED', null, ['status' => $status], ['correlation_id' => $txHash]);

            return (array) DB::table('custody_deposits')->where('id', $depositPk)->first();
        });
    }

    public function updateConfirmations(int|string $depositId, int $confirmations, ?string $blockHash = null): array
    {
        return DB::transaction(function () use ($blockHash, $confirmations, $depositId): array {
            $query = DB::table('custody_deposits')->where('deposit_id', $depositId);
            if (is_numeric($depositId)) {
                $query->orWhere('id', $depositId);
            }
            $deposit = $query->lockForUpdate()->first();
            if (!$deposit) {
                throw new RuntimeException('Deposit not found.');
            }

            $before = (array) $deposit;
            if ($blockHash !== null && $deposit->block_hash !== null && $blockHash !== $deposit->block_hash) {
                DB::table('custody_deposits')->where('id', $deposit->id)->update([
                    'status' => 'REORG_PENDING',
                    'metadata' => json_encode(array_merge(json_decode((string) ($deposit->metadata ?? '{}'), true) ?: [], ['reorg_block_hash' => $blockHash]), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
                $this->event((int) $deposit->id, 'CHAIN_REORG_DETECTED', $before, ['status' => 'REORG_PENDING']);

                return (array) DB::table('custody_deposits')->where('id', $deposit->id)->first();
            }

            $status = $confirmations >= (int) $deposit->required_confirmations ? 'CONFIRMED' : 'CONFIRMING';
            DB::table('custody_deposits')->where('id', $deposit->id)->update([
                'confirmations' => $confirmations,
                'status' => $status,
                'confirmed_at' => $status === 'CONFIRMED' ? now() : null,
                'updated_at' => now(),
            ]);
            $this->event((int) $deposit->id, 'CONFIRMATIONS_UPDATED', $before, ['status' => $status, 'confirmations' => $confirmations]);

            return (array) DB::table('custody_deposits')->where('id', $deposit->id)->first();
        });
    }

    public function creditConfirmed(int|string $depositId): array
    {
        return DB::transaction(function () use ($depositId): array {
            $query = DB::table('custody_deposits')->where('deposit_id', $depositId);
            if (is_numeric($depositId)) {
                $query->orWhere('id', $depositId);
            }
            $deposit = $query->lockForUpdate()->first();
            if (!$deposit) {
                throw new RuntimeException('Deposit not found.');
            }
            if ($deposit->status === 'CREDITED') {
                return (array) $deposit;
            }
            if ($deposit->status !== 'CONFIRMED') {
                throw new RuntimeException('Deposit is not confirmed.');
            }
            if (!$deposit->user_id) {
                throw new RuntimeException('Deposit has no user assignment.');
            }

            $reference = 'custody-deposit:' . $deposit->network . ':' . $deposit->tx_hash . ':' . $deposit->event_identifier;
            $this->settlement->depositCredit((int) $deposit->user_id, 'funding', (string) $deposit->asset, (string) $deposit->amount, $reference, [
                'source_service' => 'custody_deposit',
                'network' => $deposit->network,
                'tx_hash' => $deposit->tx_hash,
                'deposit_id' => $deposit->deposit_id,
            ]);

            DB::table('custody_deposits')->where('id', $deposit->id)->update([
                'status' => 'CREDITED',
                'ledger_reference' => $reference,
                'credited_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event((int) $deposit->id, 'DEPOSIT_CREDITED', (array) $deposit, ['status' => 'CREDITED', 'ledger_reference' => $reference]);

            return (array) DB::table('custody_deposits')->where('id', $deposit->id)->first();
        });
    }

    private function recordUnsupported(array $evidence, string $reason): array
    {
        $network = strtolower((string) ($evidence['network'] ?? ''));
        $asset = strtoupper((string) ($evidence['asset'] ?? 'UNSUPPORTED'));
        $txHash = (string) ($evidence['tx_hash'] ?? '');
        $eventIdentifier = (string) ($evidence['event_identifier'] ?? ($evidence['event_index'] ?? '0'));
        $amount = FinancialDecimal::normalize((string) ($evidence['amount'] ?? '0'));

        return DB::transaction(function () use ($amount, $asset, $eventIdentifier, $evidence, $network, $reason, $txHash): array {
            $existing = DB::table('custody_deposits')
                ->where('network', $network)
                ->where('tx_hash', $txHash)
                ->where('event_identifier', $eventIdentifier)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return (array) $existing;
            }

            $pk = DB::table('custody_deposits')->insertGetId([
                'deposit_id' => (string) Str::uuid(),
                'network' => $network,
                'asset' => $asset,
                'tx_hash' => $txHash,
                'event_identifier' => $eventIdentifier,
                'block_height' => $evidence['block_height'] ?? null,
                'block_hash' => $evidence['block_hash'] ?? null,
                'sender' => $evidence['sender'] ?? null,
                'destination' => (string) ($evidence['destination'] ?? ''),
                'memo_tag' => $evidence['memo_tag'] ?? null,
                'amount' => $amount,
                'confirmations' => (int) ($evidence['confirmations'] ?? 0),
                'required_confirmations' => 999999,
                'detection_source' => (string) ($evidence['detection_source'] ?? 'scanner'),
                'status' => $reason,
                'detected_at' => now(),
                'metadata' => json_encode(['manual_review_reason' => $reason], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($pk, 'UNSUPPORTED_DEPOSIT_DETECTED', null, ['status' => $reason]);

            return (array) DB::table('custody_deposits')->where('id', $pk)->first();
        });
    }

    private function event(int $depositPk, string $type, ?array $before, array $after, array $metadata = []): void
    {
        DB::table('custody_deposit_events')->insert([
            'custody_deposit_id' => $depositPk,
            'event_type' => $type,
            'correlation_id' => $metadata['correlation_id'] ?? null,
            'before_state' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_state' => json_encode($after, JSON_THROW_ON_ERROR),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
