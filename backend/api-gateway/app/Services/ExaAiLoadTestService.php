<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiLoadRun;
use Illuminate\Support\Str;

class ExaAiLoadTestService
{
    public function recordDecisionFanout(string $scenario, int $participants, array $metrics): ExaAiLoadRun
    {
        $failed = (int) ($metrics['failed_decisions'] ?? 0);
        $duplicates = (int) ($metrics['duplicate_decisions'] ?? 0);
        $financialFailures = (int) ($metrics['financial_invariant_failures'] ?? 0);

        return ExaAiLoadRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'scenario' => $scenario,
            'participants' => $participants,
            'metrics' => $metrics,
            'status' => $failed === 0 && $duplicates === 0 && $financialFailures === 0 ? 'passed' : 'failed',
        ]);
    }
}
