<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiPortfolio;
use App\Models\ExaAiReconciliationDifference;
use App\Models\ExaAiReconciliationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExaAiReconciliationService
{
    private const SCALE = 8;

    public function run(): ExaAiReconciliationRun
    {
        return DB::transaction(function (): ExaAiReconciliationRun {
            $run = ExaAiReconciliationRun::query()->create([
                'run_uuid' => (string) Str::uuid(),
                'status' => 'running',
                'started_at' => now(),
            ]);

            $portfoliosChecked = 0;
            $differences = 0;

            ExaAiPortfolio::query()->chunkById(100, function ($portfolios) use (&$differences, &$portfoliosChecked, $run): void {
                foreach ($portfolios as $portfolio) {
                    $portfoliosChecked++;
                    $accountingTotal = bcadd(
                        bcadd((string) $portfolio->available_amount, (string) $portfolio->reserved_amount, self::SCALE),
                        (string) $portfolio->deployed_amount,
                        self::SCALE
                    );

                    if (bccomp($accountingTotal, (string) $portfolio->allocated_amount, self::SCALE) > 0) {
                        $differences++;
                        ExaAiReconciliationDifference::query()->create([
                            'run_id' => $run->id,
                            'user_id' => $portfolio->user_id,
                            'difference_type' => 'PORTFOLIO_ALLOCATION_OVERSTATED',
                            'severity' => 'critical',
                            'evidence' => [
                                'portfolio_id' => $portfolio->id,
                                'available_reserved_deployed' => $accountingTotal,
                                'allocated_amount' => (string) $portfolio->allocated_amount,
                            ],
                        ]);
                    }
                }
            });

            $run->update([
                'status' => $differences === 0 ? 'completed' : 'completed_with_findings',
                'portfolios_checked' => $portfoliosChecked,
                'decisions_checked' => 0,
                'differences_found' => $differences,
                'summary' => [
                    'critical_findings' => $differences,
                    'auto_corrected' => false,
                ],
                'completed_at' => now(),
            ]);

            return $run->fresh('differences');
        });
    }
}
