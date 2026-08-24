<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Models\Account;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustodyReconciliationService
{
    public function run(?string $asset = null, ?string $network = null): array
    {
        $asset = $asset ? strtoupper($asset) : null;
        $network = $network ? strtolower($network) : null;

        $liabilityQuery = Account::query()->where('owner_type', 'user');
        if ($asset) {
            $liabilityQuery->where('asset', $asset);
        }
        $userLiabilities = (string) $liabilityQuery->sum('balance');

        $backingQuery = DB::table('custody_wallet_balance_snapshots');
        if ($asset) {
            $backingQuery->where('asset', $asset);
        }
        if ($network) {
            $backingQuery->where('network', $network);
        }
        $controlledBacking = (string) $backingQuery->sum('balance');

        $coverage = FinancialDecimal::compare($userLiabilities, '0') === 0
            ? '1'
            : FinancialDecimal::div($controlledBacking, $userLiabilities);
        $difference = FinancialDecimal::sub($controlledBacking, $userLiabilities);
        $status = FinancialDecimal::compare($difference, '0') >= 0 ? 'PASS' : 'UNDER_BACKED';

        return DB::transaction(function () use ($asset, $controlledBacking, $coverage, $difference, $network, $status, $userLiabilities): array {
            $runPk = DB::table('custody_reconciliation_runs')->insertGetId([
                'run_id' => (string) Str::uuid(),
                'asset' => $asset,
                'network' => $network,
                'status' => $status,
                'user_liabilities' => FinancialDecimal::normalize($userLiabilities),
                'controlled_backing' => FinancialDecimal::normalize($controlledBacking),
                'coverage_ratio' => FinancialDecimal::normalize($coverage),
                'metadata' => json_encode(['low_capital_mode' => (bool) config('custody.low_capital_mode', true)], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($status !== 'PASS') {
                DB::table('custody_reconciliation_differences')->insert([
                    'custody_reconciliation_run_id' => $runPk,
                    'severity' => 'CRITICAL',
                    'type' => 'CONTROLLED_BACKING_SHORTAGE',
                    'asset' => $asset,
                    'network' => $network,
                    'difference_amount' => $difference,
                    'metadata' => json_encode(['action' => 'restrict_treasury_allocation_and_investigate'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (array) DB::table('custody_reconciliation_runs')->where('id', $runPk)->first();
        });
    }

    public function snapshotDaily(?string $asset = null, ?string $network = null): array
    {
        $run = $this->run($asset, $network);
        $assetValue = $asset ? strtoupper($asset) : (string) ($run['asset'] ?? 'ALL');
        $networkValue = $network ? strtolower($network) : ($run['network'] ?? null);

        DB::table('custody_daily_snapshots')->updateOrInsert(
            ['snapshot_date' => now()->toDateString(), 'asset' => $assetValue, 'network' => $networkValue],
            [
                'user_liabilities' => (string) $run['user_liabilities'],
                'controlled_backing' => (string) $run['controlled_backing'],
                'difference' => FinancialDecimal::sub((string) $run['controlled_backing'], (string) $run['user_liabilities']),
                'coverage_ratio' => (string) $run['coverage_ratio'],
                'metadata' => json_encode(['reconciliation_run_id' => $run['run_id']], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (array) DB::table('custody_daily_snapshots')
            ->where('snapshot_date', now()->toDateString())
            ->where('asset', $assetValue)
            ->where('network', $networkValue)
            ->first();
    }
}
