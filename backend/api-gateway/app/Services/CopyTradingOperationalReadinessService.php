<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class CopyTradingOperationalReadinessService
{
    public function check(): array
    {
        $checks = [
            'lead_traders' => $this->tableReady('traders'),
            'relationships' => $this->tableReady('copy_relationships'),
            'lead_execution_events' => $this->tableReady('copy_lead_trade_events'),
            'copy_orders' => $this->tableReady('copy_orders'),
            'strategy_positions' => $this->tableReady('copy_strategy_positions'),
            'profit_share' => $this->tableReady('copy_profit_share_accruals'),
            'surveillance' => $this->tableReady('copy_surveillance_events'),
            'surveillance_cases' => $this->tableReady('copy_surveillance_cases'),
            'private_realtime' => $this->tableReady('copy_realtime_events'),
            'load_runs' => $this->tableReady('copy_load_runs'),
            'futures_oms' => class_exists(FuturesOrderService::class),
            'decimal_support' => $this->decimalReady(),
        ];

        $status = in_array(false, $checks, true) ? 'NOT_READY' : 'READY';

        return [
            'status' => $status,
            'checks' => $checks,
            'active_lead_traders' => DB::table('traders')->where('is_master_trader', true)->where('status', 'active')->count(),
            'active_followers' => DB::table('copy_relationships')->where('status', 'active')->distinct('follower_id')->count('follower_id'),
            'copy_aum' => (string) (DB::table('copy_relationships')->where('status', 'active')->sum('amount_allocated') ?: '0'),
            'failed_copies' => DB::table('copy_orders')->where('status', 'failed')->count(),
            'surveillance_cases_open' => $checks['surveillance_cases'] ? DB::table('copy_surveillance_cases')->whereIn('status', ['OPEN', 'REVIEWING', 'ESCALATED'])->count() : null,
            'latest_load_runs' => $checks['load_runs'] ? DB::table('copy_load_runs')->latest('id')->limit(5)->get() : [],
            'software_readiness' => $status,
            'operations_status' => config('copy_trading.operations_status', 'not_staffed'),
            'compliance_approval' => config('copy_trading.compliance_approval', 'required'),
            'production_launch_mode' => config('copy_trading.production_launch_mode', 'limited_release'),
            'production_operational_readiness' => [
                'status' => config('copy_trading.production_launch_mode', 'limited_release') === 'public'
                    && config('copy_trading.operations_status') === 'staffed'
                    && config('copy_trading.compliance_approval') === 'approved'
                        ? 'READY'
                        : 'OPERATIONAL_SETUP_REQUIRED',
                'note' => 'Software readiness is separate from public launch, staffing, compliance, and jurisdictional approvals.',
            ],
        ];
    }

    private function tableReady(string $table): bool
    {
        try {
            DB::table($table)->limit(1)->exists();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function decimalReady(): bool
    {
        try {
            FinancialDecimal::ensureAvailable();
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
