<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiDecision;
use App\Models\ExaAiOrder;
use App\Models\ExaAiStrategyVersion;

class ExaAiStrategyHealthService
{
    public function evaluate(ExaAiStrategyVersion $version): array
    {
        $decisionCount = ExaAiDecision::query()->where('strategy_version_id', $version->id)->count();
        $rejections = ExaAiDecision::query()->where('strategy_version_id', $version->id)->where('risk_decision', 'rejected')->count();
        $failures = ExaAiDecision::query()->where('strategy_version_id', $version->id)->where('status', 'failed')->count();
        $loss = (string) ExaAiOrder::query()->where('strategy_definition_id', $version->strategy_definition_id)->where('realized_pnl', '<', 0)->sum('realized_pnl');

        $rejectionRate = $decisionCount > 0 ? $rejections / $decisionCount : 0.0;
        $failureRate = $decisionCount > 0 ? $failures / $decisionCount : 0.0;
        $state = 'HEALTHY';

        if ($failureRate >= 0.2) {
            $state = 'PAUSED';
        } elseif ($rejectionRate >= 0.5) {
            $state = 'RESTRICTED';
        } elseif ($rejectionRate >= 0.2) {
            $state = 'DEGRADED';
        } elseif ((float) $loss < -1000) {
            $state = 'WATCH';
        }

        return [
            'state' => $state,
            'decision_count' => $decisionCount,
            'rejection_rate' => round($rejectionRate, 4),
            'execution_failure_rate' => round($failureRate, 4),
            'realized_loss' => $loss,
        ];
    }
}
