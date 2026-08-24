<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Models\Account;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FiatReconciliationService
{
    public function run(?string $currency = null): array
    {
        $currency = $currency ? strtoupper($currency) : null;
        $runs = [];
        $currencies = $currency ? [$currency] : DB::table('fiat_currencies')->pluck('code')->all();
        foreach ($currencies as $asset) {
            $liabilities = (string) Account::query()
                ->whereNotNull('user_id')
                ->where('asset', $asset)
                ->sum('balance');
            $providerBacking = (string) Account::query()
                ->whereNull('user_id')
                ->where('asset', $asset)
                ->whereIn('account_type', ['fiat_provider_asset', 'treasury', 'settlement_bank'])
                ->sum('balance');
            $coverage = FinancialDecimal::compare($liabilities, '0') > 0
                ? FinancialDecimal::div($providerBacking, $liabilities)
                : '1';
            $status = FinancialDecimal::compare($providerBacking, $liabilities) >= 0 ? 'PASS' : 'UNDER_BACKED';

            $pk = DB::table('fiat_reconciliation_runs')->insertGetId([
                'run_id' => (string) Str::uuid(),
                'currency' => $asset,
                'status' => $status,
                'user_liabilities' => $liabilities,
                'controlled_backing' => $providerBacking,
                'coverage_ratio' => $coverage,
                'metadata' => json_encode(['source' => 'phase10_reconciliation'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($status !== 'PASS') {
                DB::table('fiat_reconciliation_differences')->insert([
                    'fiat_reconciliation_run_id' => $pk,
                    'severity' => 'CRITICAL',
                    'type' => 'FIAT_BACKING_SHORTFALL',
                    'currency' => $asset,
                    'difference_amount' => FinancialDecimal::sub($liabilities, $providerBacking),
                    'metadata' => json_encode(['source' => 'phase10_reconciliation'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $runs[] = (array) DB::table('fiat_reconciliation_runs')->where('id', $pk)->first();
        }

        return ['status' => collect($runs)->every(fn ($run) => $run['status'] === 'PASS') ? 'PASS' : 'FAIL', 'runs' => $runs];
    }
}
