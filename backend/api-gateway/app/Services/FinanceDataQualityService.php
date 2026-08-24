<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceAccountMapping;
use App\Models\FinanceFinancialEvent;
use App\Models\FinanceJournal;
use App\Models\FinanceJournalLine;
use App\Models\FinanceReconciliationBreak;
use Illuminate\Support\Str;

class FinanceDataQualityService
{
    public function run(): array
    {
        $findings = [];
        foreach (FinanceJournal::query()->with('lines')->get() as $journal) {
            try {
                app(FinanceAccountingService::class)->assertJournalBalanced($journal);
            } catch (\Throwable $e) {
                $findings[] = $this->break('journal', 'CRITICAL', 'UNBALANCED_JOURNAL', 'finance_journal', (string) $journal->journal_uuid, ['error' => $e->getMessage()]);
            }
            if ($journal->status !== 'POSTED') {
                $findings[] = $this->break('journal', 'HIGH', 'UNPOSTED_JOURNAL', 'finance_journal', (string) $journal->journal_uuid, []);
            }
        }
        $eventsWithoutJournal = FinanceFinancialEvent::query()->whereDoesntHave('journal')->count();
        if ($eventsWithoutJournal > 0) {
            $findings[] = $this->break('event', 'HIGH', 'FINANCIAL_EVENT_WITHOUT_JOURNAL', 'finance_financial_event', 'multiple', ['count' => $eventsWithoutJournal]);
        }
        if (FinanceAccountMapping::query()->where('status', 'ACTIVE')->count() === 0 && FinanceJournalLine::query()->exists()) {
            $findings[] = $this->break('mapping', 'HIGH', 'MISSING_ACTIVE_ACCOUNT_MAPPING', 'finance_account_mapping', 'global', []);
        }
        foreach (FinanceReconciliationBreak::query()->where('status', 'OPEN')->whereIn('code', ['BACKING_CRITICAL', 'BACKING_UNKNOWN'])->get() as $break) {
            $findings[] = $break->toArray();
        }

        return ['status' => $findings === [] ? 'PASS' : 'FAIL', 'findings' => $findings, 'generated_at' => now()->toISOString()];
    }

    private function break(string $scope, string $severity, string $code, string $subjectType, string $subjectReference, array $evidence): array
    {
        return FinanceReconciliationBreak::query()->firstOrCreate([
            'scope' => $scope,
            'code' => $code,
            'subject_type' => $subjectType,
            'subject_reference' => $subjectReference,
            'status' => 'OPEN',
        ], [
            'break_uuid' => (string) Str::uuid(),
            'severity' => $severity,
            'message' => $code,
            'evidence' => $evidence,
        ])->toArray();
    }
}
