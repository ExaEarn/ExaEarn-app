<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinancialReconciliationDifference;
use App\Models\FinancialReconciliationRun;
use App\Models\InsuranceFundAccount;
use App\Models\MarginLendingPool;
use Illuminate\Support\Str;

class FinancialReconciliationService
{
    public function __construct(
        private readonly LedgerReconciliationService $ledger,
        private readonly MarginReconciliationService $margin,
        private readonly FuturesReconciliationService $futures,
        private readonly SwapReconciliationService $swap,
    ) {
    }

    public function run(): FinancialReconciliationRun
    {
        $started = now();
        $run = FinancialReconciliationRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'status' => 'PASS',
            'differences_count' => 0,
            'summary' => [],
            'started_at' => $started,
        ]);

        $differences = [];
        $ledger = $this->ledger->run();
        foreach (['balanced_transaction_failures', 'negative_user_accounts', 'reservation_integrity_failures', 'duplicate_references'] as $key) {
            foreach (($ledger[$key] ?? []) as $row) {
                $differences[] = $this->difference($run, 'ledger', $key === 'negative_user_accounts' ? 'CRITICAL' : 'HIGH', strtoupper($key), 'Ledger reconciliation difference.', (array) $row);
            }
        }

        foreach ($this->margin->run() as $finding) {
            $differences[] = $this->difference($run, 'margin', strtoupper((string) $finding->severity), 'MARGIN_RECONCILIATION', (string) $finding->message, $finding->metadata ?? []);
        }

        $futures = $this->futures->run();
        foreach (($futures['findings'] ?? []) as $finding) {
            $differences[] = $this->difference($run, 'futures', strtoupper((string) $finding->severity), 'FUTURES_RECONCILIATION', (string) $finding->message, $finding->metadata ?? []);
        }

        $swap = $this->swap->report();
        if (($swap['status'] ?? 'PASS') !== 'PASS') {
            $differences[] = $this->difference($run, 'convert', 'HIGH', 'CONVERT_RECONCILIATION', 'Convert reconciliation reported a non-pass status.', $swap);
        }

        foreach (MarginLendingPool::query()->get() as $pool) {
            if (FinancialDecimal::compare((string) $pool->available_liquidity, '0') < 0 || FinancialDecimal::compare((string) $pool->borrowed_liquidity, '0') < 0) {
                $differences[] = $this->difference($run, 'lending', 'CRITICAL', 'LENDING_POOL_DEFICIT', 'Lending pool has a negative component.', $pool->toArray());
            }
            $expected = FinancialDecimal::add((string) $pool->available_liquidity, (string) $pool->borrowed_liquidity);
            if (FinancialDecimal::compare($expected, (string) $pool->total_liquidity) > 0) {
                $differences[] = $this->difference($run, 'lending', 'CRITICAL', 'LENDING_POOL_DEFICIT', 'Lending pool accounting invariant failed.', $pool->toArray());
            }
        }

        foreach (InsuranceFundAccount::query()->get() as $fund) {
            if (FinancialDecimal::compare((string) $fund->balance, '0') < 0) {
                $differences[] = $this->difference($run, 'insurance', 'CRITICAL', 'INSURANCE_FUND_NEGATIVE', 'Insurance fund balance is negative.', $fund->toArray());
            }
        }

        $status = collect($differences)->contains(fn (FinancialReconciliationDifference $difference): bool => $difference->severity === 'CRITICAL')
            ? 'CRITICAL'
            : (count($differences) > 0 ? 'FAIL' : 'PASS');

        $run->update([
            'status' => $status,
            'differences_count' => count($differences),
            'summary' => [
                'ledger' => $this->counts($differences, 'ledger'),
                'margin' => $this->counts($differences, 'margin'),
                'futures' => $this->counts($differences, 'futures'),
                'convert' => $this->counts($differences, 'convert'),
                'lending' => $this->counts($differences, 'lending'),
                'insurance' => $this->counts($differences, 'insurance'),
            ],
            'finished_at' => now(),
        ]);

        return $run->fresh(['differences']) ?? $run;
    }

    private function difference(FinancialReconciliationRun $run, string $scope, string $severity, string $code, string $message, array $metadata): FinancialReconciliationDifference
    {
        return FinancialReconciliationDifference::query()->create([
            'financial_reconciliation_run_id' => $run->id,
            'scope' => $scope,
            'severity' => strtoupper($severity),
            'code' => $code,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param array<int, FinancialReconciliationDifference> $differences
     */
    private function counts(array $differences, string $scope): array
    {
        $filtered = array_values(array_filter($differences, static fn (FinancialReconciliationDifference $difference): bool => $difference->scope === $scope));

        return [
            'total' => count($filtered),
            'critical' => count(array_filter($filtered, static fn (FinancialReconciliationDifference $difference): bool => $difference->severity === 'CRITICAL')),
        ];
    }
}
