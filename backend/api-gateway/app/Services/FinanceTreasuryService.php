<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\FinanceAssetSource;
use App\Models\FinanceJournalLine;

class FinanceTreasuryService
{
    public function treasuryPosition(): array
    {
        return Account::query()
            ->whereNull('user_id')
            ->where(function ($q): void {
                $q->where('account_type', 'like', '%treasury%')
                    ->orWhere('account_type', 'like', '%revenue%')
                    ->orWhere('account_type', 'like', '%profit%');
            })
            ->get()
            ->groupBy('asset')
            ->map(fn ($rows, $asset): array => [
                'asset' => $asset,
                'quantity' => $rows->reduce(fn (string $carry, Account $account): string => FinancialDecimal::add($carry, (string) $account->balance), '0'),
                'sources' => FinanceAssetSource::query()->where('asset', $asset)->where('ownership_class', 'CORPORATE_TREASURY')->get()->toArray(),
            ])->values()->all();
    }

    public function pnl(): array
    {
        $rows = FinanceJournalLine::query()
            ->join('finance_chart_accounts', 'finance_chart_accounts.id', '=', 'finance_journal_lines.finance_chart_account_id')
            ->whereIn('finance_chart_accounts.category', ['REVENUE', 'EXPENSE'])
            ->selectRaw('finance_chart_accounts.category, finance_journal_lines.asset, SUM(finance_journal_lines.credit) as credits, SUM(finance_journal_lines.debit) as debits')
            ->groupBy('finance_chart_accounts.category', 'finance_journal_lines.asset')
            ->get();
        $byAsset = [];
        foreach ($rows as $row) {
            $asset = (string) $row->asset;
            $byAsset[$asset] ??= ['asset' => $asset, 'revenue' => '0', 'expenses' => '0', 'realized_pnl' => '0', 'unrealized_pnl' => '0'];
            if ($row->category === 'REVENUE') {
                $byAsset[$asset]['revenue'] = FinancialDecimal::add($byAsset[$asset]['revenue'], FinancialDecimal::sub((string) $row->credits, (string) $row->debits));
            } else {
                $byAsset[$asset]['expenses'] = FinancialDecimal::add($byAsset[$asset]['expenses'], FinancialDecimal::sub((string) $row->debits, (string) $row->credits));
            }
            $byAsset[$asset]['realized_pnl'] = FinancialDecimal::sub($byAsset[$asset]['revenue'], $byAsset[$asset]['expenses']);
        }

        return array_values($byAsset);
    }
}
