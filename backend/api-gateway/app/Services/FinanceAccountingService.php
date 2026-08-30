<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\FinanceAccountMapping;
use App\Models\FinanceChartAccount;
use App\Models\FinanceFinancialEvent;
use App\Models\FinanceJournal;
use App\Models\FinanceJournalLine;
use App\Models\FinanceClosePeriod;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FinanceAccountingService
{
    private const ROOT_ACCOUNTS = [
        ['1000', 'Assets', 'ASSET', null, 'CORPORATE_TREASURY', 'DEBIT'],
        ['1100', 'Crypto Custody', 'ASSET', '1000', 'CUSTOMER', 'DEBIT'],
        ['1200', 'Fiat Assets', 'ASSET', '1000', 'CUSTOMER', 'DEBIT'],
        ['1300', 'Receivables', 'RECEIVABLE', '1000', 'RECEIVABLE', 'DEBIT'],
        ['1400', 'Treasury Assets', 'ASSET', '1000', 'CORPORATE_TREASURY', 'DEBIT'],
        ['2000', 'Liabilities', 'LIABILITY', null, 'CUSTOMER', 'CREDIT'],
        ['2100', 'Customer Crypto Liabilities', 'LIABILITY', '2000', 'CUSTOMER', 'CREDIT'],
        ['2200', 'Customer Fiat Liabilities', 'LIABILITY', '2000', 'CUSTOMER', 'CREDIT'],
        ['2300', 'Escrow Liabilities', 'LIABILITY', '2000', 'ESCROW', 'CREDIT'],
        ['2400', 'Staking and Earn Liabilities', 'LIABILITY', '2000', 'CUSTOMER', 'CREDIT'],
        ['2500', 'Pending Withdrawal Liabilities', 'LIABILITY', '2000', 'PAYABLE', 'CREDIT'],
        ['2600', 'Institutional and MM Obligations', 'LIABILITY', '2000', 'INSTITUTIONAL', 'CREDIT'],
        ['2700', 'Payables', 'PAYABLE', '2000', 'PAYABLE', 'CREDIT'],
        ['3000', 'Equity', 'EQUITY', null, 'CORPORATE_TREASURY', 'CREDIT'],
        ['4000', 'Revenue', 'REVENUE', null, 'FEE_REVENUE', 'CREDIT'],
        ['4100', 'Trading Fee Revenue', 'REVENUE', '4000', 'FEE_REVENUE', 'CREDIT'],
        ['4200', 'Convert Revenue', 'REVENUE', '4000', 'FEE_REVENUE', 'CREDIT'],
        ['4300', 'OTC Revenue', 'REVENUE', '4000', 'FEE_REVENUE', 'CREDIT'],
        ['5000', 'Expenses', 'EXPENSE', null, 'OPERATIONS', 'DEBIT'],
        ['5100', 'Provider and Network Costs', 'EXPENSE', '5000', 'OPERATIONS', 'DEBIT'],
        ['9000', 'Suspense', 'SUSPENSE', null, 'RESTRICTED', 'DEBIT'],
    ];

    public function seedChartOfAccounts(): void
    {
        foreach (self::ROOT_ACCOUNTS as [$code, $name, $category, $parent, $ownership, $normal]) {
            FinanceChartAccount::query()->firstOrCreate(['account_code' => $code], [
                'name' => $name,
                'category' => $category,
                'parent_code' => $parent,
                'ownership_class' => $ownership,
                'normal_balance' => $normal,
                'policy_version' => (string) config('finance.policy_version', 'phase17-v1'),
            ]);
        }
    }

    public function recordLedgerEvent(LedgerTransaction $transaction, string $eventType, array $metadata = []): FinanceFinancialEvent
    {
        return DB::transaction(function () use ($eventType, $metadata, $transaction): FinanceFinancialEvent {
            $economicAt = $transaction->created_at ?? now();
            if (! ($metadata['allow_locked_period_posting'] ?? false)) {
                $this->assertPeriodOpen($economicAt);
            }
            $event = FinanceFinancialEvent::query()->firstOrCreate([
                'idempotency_key' => 'ledger:'.$transaction->reference.':'.$eventType,
            ], [
                'event_uuid' => (string) Str::uuid(),
                'event_type' => strtoupper($eventType),
                'source_service' => (string) ($metadata['source_service'] ?? $transaction->source_service ?? 'ledger'),
                'source_reference' => (string) $transaction->reference,
                'ledger_transaction_id' => $transaction->id,
                'asset' => $metadata['asset'] ?? null,
                'amount' => $metadata['amount'] ?? null,
                'status' => 'POSTED',
                'economic_at' => $economicAt,
                'metadata' => $metadata,
            ]);

            if (! $event->journal()->exists()) {
                $this->postJournalForLedgerEvent($event, $transaction);
            }

            return $event->fresh('journal.lines') ?? $event;
        });
    }

    public function assertPeriodOpen(\DateTimeInterface $economicAt): void
    {
        $date = \Carbon\Carbon::parse($economicAt)->toDateString();
        $locked = FinanceClosePeriod::query()
            ->where('status', 'APPROVED_LOCKED')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();
        if ($locked) {
            throw new RuntimeException('Finance period is locked. Post a reversal or adjustment into an open period.');
        }
    }

    public function postJournalForLedgerEvent(FinanceFinancialEvent $event, LedgerTransaction $transaction): FinanceJournal
    {
        $entries = LedgerEntry::query()->where('reference', $transaction->reference)->orderBy('id')->get();
        if ($entries->isEmpty()) {
            throw new RuntimeException('Cannot post finance journal without canonical ledger entries.');
        }

        return DB::transaction(function () use ($entries, $event, $transaction): FinanceJournal {
            $journal = FinanceJournal::query()->create([
                'journal_uuid' => (string) Str::uuid(),
                'journal_number' => 'FIN-JRN-'.str_pad((string) (FinanceJournal::query()->max('id') + 1), 10, '0', STR_PAD_LEFT),
                'financial_event_id' => $event->id,
                'ledger_transaction_id' => $transaction->id,
                'description' => $transaction->description ?: $event->event_type,
                'transaction_date' => ($event->economic_at ?? now())->toDateString(),
                'posting_date' => now()->toDateString(),
                'status' => 'POSTED',
                'reporting_currency' => (string) config('finance.reporting_currency', 'USD'),
                'posted_at' => now(),
                'metadata' => ['canonical_ledger_reference' => $transaction->reference],
            ]);

            foreach ($entries as $entry) {
                $amount = (string) $entry->amount;
                $mapping = $this->mappingForLedgerAccount(Account::query()->findOrFail($entry->account_id));
                FinanceJournalLine::query()->create([
                    'finance_journal_id' => $journal->id,
                    'finance_chart_account_id' => $mapping->finance_chart_account_id,
                    'ledger_entry_id' => $entry->id,
                    'ledger_account_id' => $entry->account_id,
                    'asset' => strtoupper((string) $entry->asset),
                    'debit' => FinancialDecimal::compare($amount, '0') < 0 ? FinancialDecimal::abs($amount) : '0',
                    'credit' => FinancialDecimal::compare($amount, '0') > 0 ? $amount : '0',
                    'reporting_currency' => (string) config('finance.reporting_currency', 'USD'),
                    'ownership_class' => $mapping->ownership_class,
                    'metadata' => ['ledger_reference' => $entry->reference, 'ledger_amount' => $amount],
                ]);
            }

            $this->assertJournalBalanced($journal->fresh('lines'));

            return $journal->fresh('lines');
        });
    }

    public function mappingForLedgerAccount(Account $account): FinanceAccountMapping
    {
        $this->seedChartOfAccounts();
        $sourceKey = strtolower((string) $account->account_type).':'.strtoupper((string) $account->asset).':'.($account->owner_type ?? 'system');
        $existing = FinanceAccountMapping::query()->where('source_type', 'ledger_account')->where('source_key', $sourceKey)->where('status', 'ACTIVE')->first();
        if ($existing) {
            return $existing;
        }

        [$code, $ownership] = $this->classifyLedgerAccount($account);
        $chart = FinanceChartAccount::query()->where('account_code', $code)->firstOrFail();

        return FinanceAccountMapping::query()->create([
            'mapping_uuid' => (string) Str::uuid(),
            'source_type' => 'ledger_account',
            'source_key' => $sourceKey,
            'finance_chart_account_id' => $chart->id,
            'ownership_class' => $ownership,
            'status' => 'ACTIVE',
            'rule_version' => (string) config('finance.policy_version', 'phase17-v1'),
            'effective_at' => now(),
        ]);
    }

    public function assertJournalBalanced(FinanceJournal $journal): void
    {
        $byAsset = $journal->lines->groupBy('asset');
        foreach ($byAsset as $asset => $lines) {
            $debit = '0';
            $credit = '0';
            foreach ($lines as $line) {
                $debit = FinancialDecimal::add($debit, (string) $line->debit);
                $credit = FinancialDecimal::add($credit, (string) $line->credit);
            }
            if (FinancialDecimal::compare($debit, $credit) !== 0) {
                throw new RuntimeException("Finance journal is unbalanced for {$asset}.");
            }
        }
    }

    private function classifyLedgerAccount(Account $account): array
    {
        $type = strtolower((string) $account->account_type);
        if (($account->owner_type ?? null) === 'user' || $account->user_id !== null) {
            if (str_contains($type, 'escrow')) {
                return ['2300', 'ESCROW'];
            }
            if (str_contains($type, 'staking') || str_contains($type, 'earn')) {
                return ['2400', 'CUSTOMER'];
            }
            if (in_array(strtoupper((string) $account->asset), ['USD', 'NGN', 'EUR', 'GBP'], true)) {
                return ['2200', 'CUSTOMER'];
            }
            return ['2100', 'CUSTOMER'];
        }
        if (str_contains($type, 'fee') || str_contains($type, 'revenue') || str_contains($type, 'profit')) {
            return [str_contains($type, 'otc') ? '4300' : '4100', 'FEE_REVENUE'];
        }
        if (str_contains($type, 'provider') || str_contains($type, 'expense') || str_contains($type, 'network')) {
            return ['5100', 'OPERATIONS'];
        }
        if (str_contains($type, 'receivable')) {
            return ['1300', 'RECEIVABLE'];
        }
        if (str_contains($type, 'payable')) {
            return ['2700', 'PAYABLE'];
        }
        if (str_contains($type, 'suspense')) {
            return ['9000', 'RESTRICTED'];
        }
        return ['1400', 'CORPORATE_TREASURY'];
    }
}
