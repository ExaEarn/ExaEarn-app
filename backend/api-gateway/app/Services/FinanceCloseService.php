<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\FinanceClosePeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class FinanceCloseService
{
    public function __construct(
        private readonly FinanceReportService $reports,
        private readonly FinanceBackingService $backing,
    ) {
    }

    public function prepare(Admin $admin, string $periodType, string $start, string $end): FinanceClosePeriod
    {
        $periodType = strtoupper($periodType);
        $periodStart = Carbon::parse($start)->toDateString();
        $periodEnd = Carbon::parse($end)->toDateString();
        $trial = $this->reports->trialBalance();
        $backing = $this->backing->calculate();
        $balanceSheet = $this->reports->balanceSheet();
        $pnl = $this->reports->profitAndLoss($periodStart, $periodEnd);
        $cashFlow = $this->reports->cashFlow($periodStart, $periodEnd);
        $status = $trial['balanced'] ? 'PREPARED' : 'BLOCKED';
        $reportSnapshots = [
            $this->reports->snapshot($periodType.'_TRIAL_BALANCE', $trial, $admin->id)->report_uuid,
            $this->reports->snapshot($periodType.'_BALANCE_SHEET', $balanceSheet, $admin->id)->report_uuid,
            $this->reports->snapshot($periodType.'_PROFIT_AND_LOSS', $pnl, $admin->id)->report_uuid,
            $this->reports->snapshot($periodType.'_CASH_FLOW', $cashFlow, $admin->id)->report_uuid,
        ];

        $period = FinanceClosePeriod::query()
            ->where('period_type', $periodType)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->first();

        if (! $period) {
            $period = new FinanceClosePeriod([
                'period_type' => $periodType,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'close_uuid' => (string) Str::uuid(),
            ]);
        }

        $period->forceFill([
            'status' => $status,
            'reporting_currency' => (string) config('finance.reporting_currency', 'USD'),
            'prepared_by_admin_id' => $admin->id,
            'prepared_at' => now(),
            'summary' => [
                'trial_balance' => $trial,
                'balance_sheet' => $balanceSheet,
                'profit_and_loss' => $pnl,
                'cash_flow' => $cashFlow,
                'backing' => $backing,
                'report_snapshots' => $reportSnapshots,
            ],
        ])->save();

        return $period->fresh();
    }

    public function approve(Admin $admin, FinanceClosePeriod $period): FinanceClosePeriod
    {
        if ((int) $period->prepared_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Financial close approval requires segregation of duties.');
        }
        if ($period->status !== 'PREPARED') {
            throw new RuntimeException('Only prepared financial close periods can be approved.');
        }
        $period->forceFill(['status' => 'APPROVED_LOCKED', 'approved_by_admin_id' => $admin->id, 'approved_at' => now()])->save();

        return $period->fresh();
    }

    public function requestReopen(Admin $admin, FinanceClosePeriod $period, string $reason): FinanceClosePeriod
    {
        if ($period->status !== 'APPROVED_LOCKED') {
            throw new RuntimeException('Only approved locked periods can be reopened.');
        }
        $period->forceFill([
            'status' => 'REOPEN_REQUESTED',
            'summary' => array_merge($period->summary ?? [], [
                'reopen_requested_by_admin_id' => $admin->id,
                'reopen_reason' => $reason,
                'reopen_requested_at' => now()->toISOString(),
            ]),
        ])->save();

        return $period->fresh();
    }

    public function approveReopen(Admin $admin, FinanceClosePeriod $period, string $reason): FinanceClosePeriod
    {
        if ($period->status !== 'REOPEN_REQUESTED') {
            throw new RuntimeException('Only reopen-requested periods can be reopened.');
        }
        if ((int) (($period->summary['reopen_requested_by_admin_id'] ?? 0)) === (int) $admin->id) {
            throw new RuntimeException('Close reopen approval requires segregation of duties.');
        }
        $period->forceFill([
            'status' => 'REOPENED',
            'summary' => array_merge($period->summary ?? [], [
                'reopened_by_admin_id' => $admin->id,
                'reopen_approval_reason' => $reason,
                'reopened_at' => now()->toISOString(),
            ]),
        ])->save();

        return $period->fresh();
    }
}
