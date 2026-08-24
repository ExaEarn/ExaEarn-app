<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\LedgerReversalLink;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LedgerReversalService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function reverse(
        string $originalReference,
        string $reversalReference,
        string $reason,
        ?string $performedByType = null,
        ?int $performedById = null,
        array $metadata = [],
    ): LedgerTransaction {
        return DB::transaction(function () use ($metadata, $originalReference, $performedById, $performedByType, $reason, $reversalReference): LedgerTransaction {
            $original = LedgerTransaction::query()->where('reference', $originalReference)->lockForUpdate()->firstOrFail();
            if ($original->status !== 'completed') {
                throw new RuntimeException('Only completed ledger transactions can be reversed.');
            }

            $existing = LedgerTransaction::query()->where('reference', $reversalReference)->first();
            if ($existing) {
                return $existing;
            }

            $entries = LedgerEntry::query()->where('reference', $originalReference)->orderBy('id')->get();
            if ($entries->isEmpty()) {
                throw new RuntimeException('Original ledger transaction has no entries.');
            }

            $reversalEntries = $entries->map(fn (LedgerEntry $entry): array => [
                'account_id' => $entry->account_id,
                'amount' => FinancialDecimal::sub('0', (string) $entry->amount),
                'asset' => $entry->asset,
                'user_id' => $entry->user_id,
                'metadata' => array_merge($metadata, [
                    'reversal_of_reference' => $originalReference,
                    'reversal_reason' => $reason,
                    'original_entry_id' => $entry->id,
                ]),
            ])->all();

            $reversal = $this->ledger->postDoubleEntry(
                $reversalReference,
                'Reversal: ' . $reason,
                $reversalEntries,
                'reversal',
                array_merge($metadata, ['reversal_of_reference' => $originalReference]),
            );

            $reversal->forceFill([
                'reversal_of_transaction_id' => $original->id,
                'initiated_by_type' => $performedByType,
                'initiated_by_id' => $performedById,
            ])->save();

            LedgerReversalLink::query()->firstOrCreate([
                'original_transaction_id' => $original->id,
                'reversal_transaction_id' => $reversal->id,
            ], [
                'reason' => $reason,
                'performed_by_type' => $performedByType,
                'performed_by_id' => $performedById,
                'metadata' => $metadata,
            ]);

            return $reversal->fresh();
        });
    }
}
