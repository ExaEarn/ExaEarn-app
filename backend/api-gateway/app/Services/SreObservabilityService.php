<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SreOperationalAlert;
use App\Models\SreSloDefinition;
use Illuminate\Support\Str;

class SreObservabilityService
{
    public function seedCoreSloDefinitions(): array
    {
        $definitions = [
            ['service_id' => 'api-gateway', 'sli_key' => 'readiness_pass_rate', 'target' => '99.90', 'window' => '30d', 'error_budget_policy' => 'DEGRADE_ON_BUDGET_EXHAUSTION'],
            ['service_id' => 'canonical-ledger', 'sli_key' => 'financial_invariant_pass_rate', 'target' => '100.00', 'window' => '30d', 'error_budget_policy' => 'EMERGENCY_ON_FAILURE'],
            ['service_id' => 'spot-engine', 'sli_key' => 'order_acceptance_latency_p95', 'target' => '250ms', 'window' => '7d', 'error_budget_policy' => 'NEW_RISK_DISABLED_ON_BREACH'],
            ['service_id' => 'market-data', 'sli_key' => 'freshness_p95', 'target' => '1000ms', 'window' => '7d', 'error_budget_policy' => 'REFERENCE_FAILOVER_THEN_DISABLE_NEW_RISK'],
            ['service_id' => 'custody', 'sli_key' => 'rpc_correct_chain_rate', 'target' => '100.00', 'window' => '30d', 'error_budget_policy' => 'PAUSE_WITHDRAWALS_ON_BREACH'],
            ['service_id' => 'finance', 'sli_key' => 'reconciliation_pass_rate', 'target' => '100.00', 'window' => '30d', 'error_budget_policy' => 'SAFE_MODE_ON_BREACH'],
            ['service_id' => 'security', 'sli_key' => 'risk_engine_available_rate', 'target' => '99.95', 'window' => '30d', 'error_budget_policy' => 'FAIL_CLOSED_ON_BREACH'],
        ];

        return array_map(fn (array $definition): SreSloDefinition => SreSloDefinition::query()->updateOrCreate([
            'service_id' => $definition['service_id'],
            'sli_key' => $definition['sli_key'],
            'window' => $definition['window'],
        ], [
            'slo_uuid' => (string) (SreSloDefinition::query()
                ->where('service_id', $definition['service_id'])
                ->where('sli_key', $definition['sli_key'])
                ->where('window', $definition['window'])
                ->value('slo_uuid') ?: Str::uuid()),
            'target' => $definition['target'],
            'error_budget_policy' => $definition['error_budget_policy'],
            'status' => 'ACTIVE',
            'metadata' => ['phase' => 19],
        ]), $definitions);
    }

    public function triggerAlert(string $key, string $severity, array $evidence = [], ?string $serviceId = null, ?string $component = null): SreOperationalAlert
    {
        return SreOperationalAlert::query()->updateOrCreate([
            'alert_key' => $key,
            'status' => 'OPEN',
        ], [
            'alert_uuid' => (string) (SreOperationalAlert::query()
                ->where('alert_key', $key)
                ->where('status', 'OPEN')
                ->value('alert_uuid') ?: Str::uuid()),
            'severity' => strtoupper($severity),
            'service_id' => $serviceId,
            'component' => $component,
            'evidence' => $evidence,
            'last_triggered_at' => now(),
            'metadata' => ['deduplicated' => true],
        ]);
    }

    public function acknowledge(SreOperationalAlert $alert): SreOperationalAlert
    {
        $alert->forceFill([
            'status' => 'ACKNOWLEDGED',
            'acknowledged_at' => now(),
        ])->save();

        return $alert->fresh();
    }

    public function resolve(SreOperationalAlert $alert, array $metadata = []): SreOperationalAlert
    {
        $alert->forceFill([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
            'metadata' => array_merge($alert->metadata ?? [], $metadata),
        ])->save();

        return $alert->fresh();
    }

    public function metricsCatalog(): array
    {
        return [
            'sre_health_snapshots_total',
            'sre_dependency_failures_total',
            'sre_queue_backlog_critical',
            'sre_dead_workers_total',
            'sre_recovery_actions_total',
            'sre_open_alerts_total',
            'sre_backup_restore_tests_total',
        ];
    }

    public function readiness(): array
    {
        $this->seedCoreSloDefinitions();

        return [
            'status' => 'READY',
            'structured_logging' => 'READY',
            'distributed_tracing' => 'READY_FOR_EXPORTER',
            'metrics' => $this->metricsCatalog(),
            'sli_count' => SreSloDefinition::query()->where('status', 'ACTIVE')->count(),
            'open_alerts' => SreOperationalAlert::query()->where('status', 'OPEN')->count(),
            'paging_integration' => 'OPERATIONAL_SETUP_REQUIRED',
        ];
    }
}

