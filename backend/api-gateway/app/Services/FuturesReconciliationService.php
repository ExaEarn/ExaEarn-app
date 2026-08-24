<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;
use App\Models\FuturesReconciliationFinding;
use Illuminate\Support\Str;

class FuturesReconciliationService
{
    public function run(): array
    {
        $findings = [];

        FuturesPosition::query()->where('status', 'open')->chunkById(100, function ($positions) use (&$findings): void {
            foreach ($positions as $position) {
                if (FinancialDecimal::compare((string) $position->quantity, '0') <= 0) {
                    $findings[] = $this->finding('position', (string) $position->symbol, 'critical', 'Open futures position has non-positive quantity.', ['position_id' => $position->id]);
                }

                if (FinancialDecimal::compare((string) $position->maintenance_margin, '0') < 0) {
                    $findings[] = $this->finding('position', (string) $position->symbol, 'critical', 'Futures position has negative maintenance margin.', ['position_id' => $position->id]);
                }
            }
        });

        return ['status' => $findings === [] ? 'pass' : 'fail', 'findings' => $findings];
    }

    private function finding(string $scope, string $symbol, string $severity, string $message, array $metadata): FuturesReconciliationFinding
    {
        return FuturesReconciliationFinding::query()->create([
            'finding_id' => 'fut-rec-' . Str::uuid(),
            'scope' => $scope,
            'symbol' => $symbol,
            'severity' => $severity,
            'status' => 'open',
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
