<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceObligation;
use Illuminate\Support\Str;
use RuntimeException;

class FinanceObligationService
{
    public function create(string $type, string $counterpartyType, string $sourceService, string $sourceReference, string $asset, string $amount, array $metadata = []): FinanceObligation
    {
        return FinanceObligation::query()->firstOrCreate([
            'obligation_type' => strtoupper($type),
            'source_service' => $sourceService,
            'source_reference' => $sourceReference,
        ], [
            'obligation_uuid' => (string) Str::uuid(),
            'counterparty_type' => strtoupper($counterpartyType),
            'counterparty_reference' => $metadata['counterparty_reference'] ?? null,
            'asset' => strtoupper($asset),
            'original_amount' => FinancialDecimal::normalize($amount),
            'outstanding_amount' => FinancialDecimal::normalize($amount),
            'status' => 'OPEN',
            'due_date' => $metadata['due_date'] ?? null,
            'ledger_reference' => $metadata['ledger_reference'] ?? null,
            'metadata' => $metadata,
        ]);
    }

    public function settle(FinanceObligation $obligation, string $amount): FinanceObligation
    {
        $amount = FinancialDecimal::normalize($amount);
        if (FinancialDecimal::compare($amount, (string) $obligation->outstanding_amount) > 0) {
            throw new RuntimeException('Settlement cannot exceed outstanding obligation.');
        }
        $remaining = FinancialDecimal::sub((string) $obligation->outstanding_amount, $amount);
        $status = FinancialDecimal::compare($remaining, '0') === 0 ? 'SETTLED' : 'PARTIALLY_SETTLED';
        $obligation->forceFill(['outstanding_amount' => $remaining, 'status' => $status])->save();

        return $obligation->fresh();
    }

    public function mark(FinanceObligation $obligation, string $status): FinanceObligation
    {
        $status = strtoupper($status);
        if (! in_array($status, ['OPEN', 'PARTIALLY_SETTLED', 'SETTLED', 'OVERDUE', 'DISPUTED', 'WRITTEN_OFF'], true)) {
            throw new RuntimeException('Unsupported finance obligation status.');
        }
        $obligation->forceFill(['status' => $status])->save();

        return $obligation->fresh();
    }
}
