<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceFinancialEvent;
use App\Models\FinanceJournal;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;

class StakingReconciliationService
{
    public function run(): array
    {
        $findings = [];

        foreach (DB::table('staking_positions')->join('staking_assets', 'staking_assets.id', '=', 'staking_positions.staking_asset_id')->select('staking_positions.*', 'staking_assets.symbol')->get() as $position) {
            $pendingLedger = $this->ledgerBalance((int) $position->user_id, 'staking_pending', (string) $position->symbol);
            $activeLedger = $this->ledgerBalance((int) $position->user_id, 'staking_active', (string) $position->symbol);
            $pendingUnstakeLedger = $this->ledgerBalance((int) $position->user_id, 'staking_pending_unstake', (string) $position->symbol);
            $payableLedger = $this->ledgerBalance((int) $position->user_id, 'staking_reward_payable', (string) $position->symbol);

            if (FinancialDecimal::compare($pendingLedger, '0') < 0 || FinancialDecimal::compare($activeLedger, '0') < 0 || FinancialDecimal::compare($pendingUnstakeLedger, '0') < 0 || FinancialDecimal::compare($payableLedger, '0') < 0) {
                $findings[] = $this->finding('CRITICAL', 'NEGATIVE_STAKING_LEDGER_BALANCE', (int) $position->id, compact('pendingLedger', 'activeLedger', 'pendingUnstakeLedger', 'payableLedger'));
            }

            if (FinancialDecimal::compare((string) $position->pending_stake_amount, '0') > 0 && ! in_array($position->status, ['pending', 'batching', 'awaiting_signature', 'delegation_submitted', 'awaiting_activation'], true)) {
                $findings[] = $this->finding('HIGH', 'PENDING_PRINCIPAL_IN_FINAL_STATE', (int) $position->id, ['status' => $position->status, 'amount' => (string) $position->pending_stake_amount]);
            }

            if (FinancialDecimal::compare((string) $position->pending_unstake_amount, '0') > 0 && ! in_array($position->status, ['unstaking', 'partial_unstake_pending', 'unbonding'], true)) {
                $findings[] = $this->finding('HIGH', 'PENDING_UNSTAKE_IN_INVALID_STATE', (int) $position->id, ['status' => $position->status, 'amount' => (string) $position->pending_unstake_amount]);
            }

            $claimable = FinancialDecimal::sub((string) $position->total_native_net_rewards, (string) $position->claimed_native_rewards);
            if (FinancialDecimal::compare($claimable, '0') < 0) {
                $findings[] = $this->finding('CRITICAL', 'CLAIMED_REWARD_EXCEEDS_VERIFIED_REWARD', (int) $position->id, ['claimable' => $claimable]);
            }
            if (FinancialDecimal::compare($claimable, $payableLedger) > 0) {
                $findings[] = $this->finding('HIGH', 'REWARD_PAYABLE_LEDGER_SHORTFALL', (int) $position->id, ['claimable' => $claimable, 'payable_ledger' => $payableLedger]);
            }
        }

        $unbalancedReports = DB::table('staking_reconciliation_reports')->where('status', 'difference_detected')->count();
        if ($unbalancedReports > 0) {
            $findings[] = $this->finding('HIGH', 'OPEN_STAKING_RECONCILIATION_REPORTS', 0, ['count' => $unbalancedReports]);
        }

        $unknownDelegations = DB::table('staking_delegation_batches')->whereIn('status', ['provider_unknown', 'unknown', 'timeout'])->count();
        if ($unknownDelegations > 0) {
            $findings[] = $this->finding('HIGH', 'PROVIDER_UNKNOWN_REQUIRES_RECONCILIATION', 0, ['count' => $unknownDelegations]);
        }

        foreach (LedgerTransaction::query()->whereIn('transaction_type', [
            'staking_reservation',
            'staking_principal_transfer',
            'staking_principal_release',
            'staking_reward_distribution',
            'staking_reward_claim',
        ])->get() as $transaction) {
            if (! FinanceFinancialEvent::query()->where('ledger_transaction_id', $transaction->id)->exists()) {
                $findings[] = $this->finding('HIGH', 'STAKING_LEDGER_EVENT_WITHOUT_ACCOUNTING_EVENT', $transaction->id, ['reference' => $transaction->reference, 'type' => $transaction->transaction_type]);
            }
        }

        foreach (FinanceJournal::query()->whereHas('event', fn ($query) => $query->where('source_service', 'staking'))->with('lines')->get() as $journal) {
            try {
                app(FinanceAccountingService::class)->assertJournalBalanced($journal);
            } catch (\Throwable $exception) {
                $findings[] = $this->finding('CRITICAL', 'UNBALANCED_STAKING_FINANCE_JOURNAL', $journal->id, ['message' => $exception->getMessage()]);
            }
        }

        return [
            'status' => $findings === [] ? 'PASS' : 'FAIL',
            'findings' => $findings,
            'checked_at' => now()->toISOString(),
        ];
    }

    private function ledgerBalance(int $userId, string $accountType, string $asset): string
    {
        $balance = DB::table('accounts')
            ->where('user_id', $userId)
            ->where('account_type', $accountType)
            ->where('asset', strtoupper($asset))
            ->value('balance');

        return FinancialDecimal::normalize((string) ($balance ?? '0'));
    }

    private function finding(string $severity, string $code, int $subjectId, array $evidence): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'subject_id' => $subjectId,
            'evidence' => $evidence,
        ];
    }
}
