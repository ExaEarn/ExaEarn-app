<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceJournalLine;
use App\Models\FinanceReportSnapshot;
use Illuminate\Support\Str;

class FinanceReportService
{
    public function trialBalance(): array
    {
        $rows = FinanceJournalLine::query()
            ->join('finance_chart_accounts', 'finance_chart_accounts.id', '=', 'finance_journal_lines.finance_chart_account_id')
            ->selectRaw('finance_chart_accounts.account_code, finance_chart_accounts.name, finance_chart_accounts.category, finance_journal_lines.asset, SUM(finance_journal_lines.debit) as debit, SUM(finance_journal_lines.credit) as credit')
            ->groupBy('finance_chart_accounts.account_code', 'finance_chart_accounts.name', 'finance_chart_accounts.category', 'finance_journal_lines.asset')
            ->orderBy('finance_chart_accounts.account_code')
            ->get()
            ->map(fn ($row): array => [
                'account_code' => $row->account_code,
                'name' => $row->name,
                'category' => $row->category,
                'asset' => $row->asset,
                'debit' => (string) $row->debit,
                'credit' => (string) $row->credit,
                'net' => FinancialDecimal::sub((string) $row->debit, (string) $row->credit),
            ])->all();

        $totalDebit = '0';
        $totalCredit = '0';
        foreach ($rows as $row) {
            $totalDebit = FinancialDecimal::add($totalDebit, $row['debit']);
            $totalCredit = FinancialDecimal::add($totalCredit, $row['credit']);
        }

        return ['rows' => $rows, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit, 'balanced' => FinancialDecimal::compare($totalDebit, $totalCredit) === 0];
    }

    public function balanceSheet(): array
    {
        $trial = $this->trialBalance();
        $totals = ['assets' => '0', 'liabilities' => '0', 'equity' => '0'];
        foreach ($trial['rows'] as $row) {
            $balance = FinancialDecimal::abs($row['net']);
            if (in_array($row['category'], ['ASSET', 'RECEIVABLE', 'CONTRA_ASSET', 'SUSPENSE'], true)) {
                $totals['assets'] = FinancialDecimal::add($totals['assets'], $balance);
            } elseif (in_array($row['category'], ['LIABILITY', 'PAYABLE', 'CONTRA_LIABILITY', 'RESERVE'], true)) {
                $totals['liabilities'] = FinancialDecimal::add($totals['liabilities'], $balance);
            } elseif (in_array($row['category'], ['EQUITY', 'REVENUE'], true)) {
                $totals['equity'] = FinancialDecimal::add($totals['equity'], $balance);
            } elseif ($row['category'] === 'EXPENSE') {
                $totals['equity'] = FinancialDecimal::sub($totals['equity'], $balance);
            }
        }
        $right = FinancialDecimal::add($totals['liabilities'], $totals['equity']);
        return array_merge($totals, ['equation_balanced' => FinancialDecimal::compare($totals['assets'], $right) === 0]);
    }

    public function profitAndLoss(?string $start = null, ?string $end = null): array
    {
        $rows = FinanceJournalLine::query()
            ->join('finance_chart_accounts', 'finance_chart_accounts.id', '=', 'finance_journal_lines.finance_chart_account_id')
            ->join('finance_journals', 'finance_journals.id', '=', 'finance_journal_lines.finance_journal_id')
            ->whereIn('finance_chart_accounts.category', ['REVENUE', 'EXPENSE'])
            ->when($start, fn ($q) => $q->whereDate('finance_journals.transaction_date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('finance_journals.transaction_date', '<=', $end))
            ->selectRaw('finance_chart_accounts.category, finance_chart_accounts.account_code, finance_chart_accounts.name, finance_journal_lines.asset, SUM(finance_journal_lines.credit) as credits, SUM(finance_journal_lines.debit) as debits')
            ->groupBy('finance_chart_accounts.category', 'finance_chart_accounts.account_code', 'finance_chart_accounts.name', 'finance_journal_lines.asset')
            ->get();
        $revenue = '0';
        $expense = '0';
        foreach ($rows as $row) {
            if ($row->category === 'REVENUE') {
                $revenue = FinancialDecimal::add($revenue, FinancialDecimal::sub((string) $row->credits, (string) $row->debits));
            } else {
                $expense = FinancialDecimal::add($expense, FinancialDecimal::sub((string) $row->debits, (string) $row->credits));
            }
        }
        return ['rows' => $rows->toArray(), 'revenue' => $revenue, 'expenses' => $expense, 'net_income' => FinancialDecimal::sub($revenue, $expense)];
    }

    public function cashFlow(?string $start = null, ?string $end = null): array
    {
        $rows = FinanceJournalLine::query()
            ->join('finance_chart_accounts', 'finance_chart_accounts.id', '=', 'finance_journal_lines.finance_chart_account_id')
            ->join('finance_journals', 'finance_journals.id', '=', 'finance_journal_lines.finance_journal_id')
            ->whereIn('finance_chart_accounts.category', ['ASSET', 'RECEIVABLE', 'PAYABLE'])
            ->when($start, fn ($q) => $q->whereDate('finance_journals.transaction_date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('finance_journals.transaction_date', '<=', $end))
            ->selectRaw('finance_journal_lines.asset, SUM(finance_journal_lines.debit) as inflow, SUM(finance_journal_lines.credit) as outflow')
            ->groupBy('finance_journal_lines.asset')
            ->get()
            ->map(fn ($row): array => [
                'asset' => $row->asset,
                'inflow' => (string) $row->inflow,
                'outflow' => (string) $row->outflow,
                'net' => FinancialDecimal::sub((string) $row->inflow, (string) $row->outflow),
            ])->all();
        return ['rows' => $rows];
    }

    public function generalLedger(?string $accountCode = null): array
    {
        return FinanceJournalLine::query()
            ->join('finance_chart_accounts', 'finance_chart_accounts.id', '=', 'finance_journal_lines.finance_chart_account_id')
            ->join('finance_journals', 'finance_journals.id', '=', 'finance_journal_lines.finance_journal_id')
            ->when($accountCode, fn ($q) => $q->where('finance_chart_accounts.account_code', $accountCode))
            ->orderBy('finance_journals.transaction_date')
            ->orderBy('finance_journal_lines.id')
            ->get([
                'finance_journals.journal_number',
                'finance_journals.description',
                'finance_journals.transaction_date',
                'finance_chart_accounts.account_code',
                'finance_chart_accounts.name',
                'finance_journal_lines.asset',
                'finance_journal_lines.debit',
                'finance_journal_lines.credit',
                'finance_journal_lines.ledger_entry_id',
                'finance_journal_lines.ledger_account_id',
                'finance_journal_lines.ownership_class',
            ])->toArray();
    }

    public function snapshot(string $type, array $payload, ?int $adminId = null): FinanceReportSnapshot
    {
        return FinanceReportSnapshot::query()->create([
            'report_uuid' => (string) Str::uuid(),
            'report_type' => strtoupper($type),
            'reporting_currency' => (string) config('finance.reporting_currency', 'USD'),
            'status' => 'GENERATED',
            'version' => (string) config('finance.policy_version', 'phase17-v1'),
            'payload' => $payload,
            'generated_by_admin_id' => $adminId,
            'generated_at' => now(),
        ]);
    }
}
