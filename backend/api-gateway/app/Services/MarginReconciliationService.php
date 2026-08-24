<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginLendingPool;
use App\Models\MarginLoan;
use App\Models\MarginReconciliationFinding;
use Illuminate\Support\Str;

class MarginReconciliationService
{
    public function run(): array
    {
        $findings = [];

        foreach (MarginLendingPool::query()->get() as $pool) {
            if (FinancialDecimal::compare((string) $pool->available_liquidity, '0') < 0 || FinancialDecimal::compare((string) $pool->borrowed_liquidity, '0') < 0) {
                $findings[] = $this->record('pool', 'CRITICAL', 'Negative margin lending pool value detected.', ['asset' => $pool->asset]);
            }
            $expectedTotal = FinancialDecimal::add((string) $pool->available_liquidity, (string) $pool->borrowed_liquidity);
            if (FinancialDecimal::compare($expectedTotal, (string) $pool->total_liquidity) > 0) {
                $findings[] = $this->record('pool', 'HIGH', 'Available plus borrowed liquidity exceeds total liquidity.', ['asset' => $pool->asset]);
            }
        }

        foreach (MarginLoan::query()->whereIn('status', [MarginLoan::STATUS_ACTIVE, MarginLoan::STATUS_PARTIALLY_REPAID])->get() as $loan) {
            if (FinancialDecimal::compare((string) $loan->principal, '0') < 0 || FinancialDecimal::compare((string) $loan->accrued_interest, '0') < 0) {
                $findings[] = $this->record('loan', 'CRITICAL', 'Negative margin loan amount detected.', ['loan_uuid' => $loan->loan_uuid]);
            }
        }

        return $findings;
    }

    private function record(string $scope, string $severity, string $message, array $metadata): MarginReconciliationFinding
    {
        return MarginReconciliationFinding::query()->create([
            'finding_id' => (string) Str::uuid(),
            'scope' => $scope,
            'severity' => $severity,
            'status' => 'OPEN',
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
