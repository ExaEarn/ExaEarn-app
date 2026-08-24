<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceJournal;
use App\Models\FinanceReconciliationBreak;

class FinanceReadinessService
{
    public function __construct(
        private readonly LedgerReconciliationService $ledger,
        private readonly FinanceReportService $reports,
        private readonly FinanceBackingService $backing,
    ) {
    }

    public function evaluate(): array
    {
        $reasons = [];
        $ledger = $this->ledger->run();
        $ledgerHasBreaks = collect($ledger)
            ->except('generated_at')
            ->contains(fn ($rows): bool => is_array($rows) && count($rows) > 0);
        if ($ledgerHasBreaks) {
            $reasons[] = 'LEDGER_RECONCILIATION_NOT_PASSING';
        }
        $trial = $this->reports->trialBalance();
        if (! $trial['balanced']) {
            $reasons[] = 'TRIAL_BALANCE_UNBALANCED';
        }
        $backing = $this->backing->calculate();
        foreach ($backing as $asset => $row) {
            if (in_array($row['status'], ['CRITICAL', 'UNKNOWN'], true) && FinancialDecimal::compare((string) $row['liability'], '0') > 0) {
                $reasons[] = 'BACKING_'.$row['status'].'_'.$asset;
            }
        }
        if (FinanceJournal::query()->where('status', 'DRAFT')->exists()) {
            $reasons[] = 'UNPOSTED_JOURNALS';
        }
        if (FinanceReconciliationBreak::query()->where('status', 'OPEN')->where('severity', 'CRITICAL')->exists()) {
            $reasons[] = 'OPEN_CRITICAL_RECONCILIATION_BREAKS';
        }

        $status = $reasons === [] ? 'READY' : (collect($reasons)->contains(fn (string $r): bool => str_contains($r, 'CRITICAL') || str_contains($r, 'LEDGER')) ? 'CRITICAL' : 'DEGRADED');

        return [
            'status' => $status,
            'reason_codes' => array_values(array_unique($reasons)),
            'trial_balance' => ['balanced' => $trial['balanced'], 'total_debit' => $trial['total_debit'], 'total_credit' => $trial['total_credit']],
            'backing' => $backing,
            'evaluated_at' => now()->toISOString(),
        ];
    }
}
