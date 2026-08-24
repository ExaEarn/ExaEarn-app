<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiDecision;
use App\Models\ExaAiLoadRun;
use App\Models\ExaAiMarketEligibility;
use App\Models\ExaAiPortfolio;
use App\Models\ExaAiRealtimeEvent;
use App\Models\ExaAiReconciliationDifference;
use App\Models\ExaAiStrategyVersion;

class ExaAiOperationalReadinessService
{
    public function __construct(private readonly ExaAiOperationsService $operations)
    {
    }

    public function report(): array
    {
        $hasMarkets = ExaAiMarketEligibility::query()->where('status', 'enabled')->exists();
        $hasStrategies = ExaAiStrategyVersion::query()
            ->where(function ($query): void {
                $query->where('state', 'active')->orWhereNull('state');
            })
            ->exists();
        $hasRealtime = ExaAiRealtimeEvent::query()->exists();
        $hasRejectedRisk = ExaAiDecision::query()->where('risk_decision', 'rejected')->exists();
        $hasPortfolios = ExaAiPortfolio::query()->exists();
        $hasOpenCriticalRecon = ExaAiReconciliationDifference::query()->where('severity', 'critical')->exists();
        $load1k = ExaAiLoadRun::query()->where('scenario', 'exaai_1k_decisions')->where('status', 'passed')->exists();
        $load10k = ExaAiLoadRun::query()->where('scenario', 'exaai_10k_decisions')->where('status', 'passed')->exists();

        $softwareReady = $hasMarkets && $hasStrategies && ! $hasOpenCriticalRecon;

        $operations = $this->operations->evaluate();

        return [
            'strategy_orchestration' => $hasStrategies ? 'READY' : 'NOT_READY',
            'market_eligibility' => $hasMarkets ? 'READY' : 'NOT_READY',
            'portfolio_allocation' => $hasPortfolios ? 'READY' : 'NOT_READY',
            'risk_engine' => $hasRejectedRisk ? 'PASS' : 'READY',
            'private_realtime' => $hasRealtime ? 'READY' : 'NOT_READY',
            'reconciliation' => $hasOpenCriticalRecon ? 'FAIL' : 'PASS',
            'load_1k' => $load1k ? 'PASS' : 'NOT_RUN',
            'load_10k' => $load10k ? 'PASS' : 'NOT_RUN',
            'phase13_backend' => $softwareReady ? 'READY' : 'NOT_READY',
            'exaai_system_operations' => 'READY',
            'operational_health' => $operations['mode'],
            'human_operations_staffing' => 'FOUNDER-MANAGED',
            'regulatory_external_approval' => 'PENDING',
            'production_launch' => 'REGULATORY_APPROVAL_PENDING',
            'safe_to_begin_phase14' => $softwareReady ? 'YES' : 'NO',
            'operations' => $operations,
        ];
    }
}
