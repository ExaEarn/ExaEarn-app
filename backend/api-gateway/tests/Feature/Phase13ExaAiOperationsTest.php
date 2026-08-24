<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExaAiDecision;
use App\Models\ExaAiMarketEligibility;
use App\Models\ExaAiOperationalAlert;
use App\Models\ExaAiOperationalIncident;
use App\Models\ExaAiPortfolio;
use App\Models\ExaAiPublicSetting;
use App\Models\ExaAiReconciliationDifference;
use App\Models\ExaAiReconciliationRun;
use App\Models\ExaAiStrategyTransition;
use App\Models\ExaAiStrategyVersion;
use App\Models\User;
use App\Services\ExaAiOperationalAlertService;
use App\Services\ExaAiOperationsService;
use App\Services\ExaAiStrategyGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase13ExaAiOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_engine_reports_healthy_system_operations_separate_from_regulatory_approval(): void
    {
        $this->seedOperationalBaseline();

        $status = app(ExaAiOperationsService::class)->evaluate();

        $this->assertSame('HEALTHY', $status['overall_status']);
        $this->assertSame('READY', $status['system_operations']);
        $this->assertSame('FOUNDER-MANAGED', $status['human_operations_staffing']);
        $this->assertSame('PENDING', $status['regulatory_external_approval']);
    }

    public function test_reconciliation_mismatch_triggers_emergency_fail_safe_and_incident(): void
    {
        $this->seedOperationalBaseline();
        $run = ExaAiReconciliationRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'status' => 'completed_with_findings',
            'differences_found' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        ExaAiReconciliationDifference::query()->create([
            'run_id' => $run->id,
            'difference_type' => 'LEDGER_ATTRIBUTION_MISMATCH',
            'severity' => 'critical',
            'evidence' => ['expected' => 'balanced', 'actual' => 'mismatch'],
        ]);

        $status = app(ExaAiOperationsService::class)->evaluate();

        $this->assertSame('EMERGENCY', $status['mode']);
        $this->assertDatabaseHas('exaai_public_settings', ['key' => 'global_controls']);
        $this->assertSame('EMERGENCY', ExaAiPublicSetting::query()->where('key', 'global_controls')->firstOrFail()->value['state']);
        $this->assertSame(1, ExaAiOperationalIncident::query()->where('incident_type', 'CRITICAL_RECONCILIATION_MISMATCH')->count());
    }

    public function test_alerts_are_deduplicated_and_can_be_resolved(): void
    {
        $alerts = app(ExaAiOperationalAlertService::class);

        $alerts->trigger('HIGH', 'market_data', 'STALE_MARKET_DATA', 'stale feed');
        $alerts->trigger('HIGH', 'market_data', 'STALE_MARKET_DATA', 'stale feed again');

        $this->assertSame(1, ExaAiOperationalAlert::query()->where('status', 'OPEN')->count());

        $alerts->resolve('market_data', 'STALE_MARKET_DATA');

        $this->assertSame(0, ExaAiOperationalAlert::query()->where('status', 'OPEN')->count());
        $this->assertSame(1, ExaAiOperationalAlert::query()->where('status', 'RESOLVED')->count());
    }

    public function test_strategy_governance_records_transition_audit(): void
    {
        $version = ExaAiStrategyVersion::query()->where('version', '1.0.0')->firstOrFail();

        app(ExaAiStrategyGovernanceService::class)->transition($version, 'LIMITED_PRODUCTION', 'limited rollout validation');

        $this->assertDatabaseHas('exaai_strategy_versions', [
            'id' => $version->id,
            'state' => 'limited_production',
        ]);
        $this->assertSame(1, ExaAiStrategyTransition::query()->where('strategy_version_id', $version->id)->count());
    }

    public function test_market_auto_disable_blocks_unsafe_ai_exposure(): void
    {
        $this->seedOperationalBaseline([
            'metadata' => [
                'current_liquidity' => '10.00000000',
                'spread_bps' => 500,
            ],
        ]);

        $disabled = app(ExaAiOperationsService::class)->autoDisableUnsafeMarkets();

        $this->assertSame(1, $disabled);
        $this->assertDatabaseHas('exaai_market_eligibilities', [
            'symbol' => 'BTCUSDT',
            'product' => 'spot',
            'status' => 'paused',
        ]);
        $this->assertDatabaseHas('exaai_operational_alerts', [
            'condition' => 'AI_MARKET_AUTO_DISABLED',
            'status' => 'OPEN',
        ]);
    }

    public function test_stale_decision_cleanup_cannot_execute_old_risk(): void
    {
        $user = User::factory()->create();
        ExaAiDecision::query()->create([
            'decision_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => 'old-risk',
            'product' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'requested_notional' => '100.00000000',
            'approved_notional' => '100.00000000',
            'reference_price' => '100000.00000000',
            'quantity' => '0.00100000',
            'confidence' => 80,
            'risk_decision' => 'approved',
            'status' => 'approved',
            'sequence' => 1,
            'decided_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinute(),
        ]);

        $expired = app(ExaAiOperationsService::class)->expireStaleDecisions();

        $this->assertSame(1, $expired);
        $this->assertDatabaseHas('exaai_decisions', [
            'idempotency_key' => 'old-risk',
            'status' => 'skipped',
            'reason_code' => 'REJECT_STALE_DECISION',
        ]);
    }

    public function test_safe_resume_blocks_unresolved_critical_incidents_and_recovers_after_resolution(): void
    {
        $this->seedOperationalBaseline();
        ExaAiOperationalIncident::query()->create([
            'incident_uuid' => (string) Str::uuid(),
            'severity' => 'SEV1',
            'status' => 'OPEN',
            'component' => 'ledger',
            'incident_type' => 'FINANCIAL_INVARIANT_FAILURE',
        ]);

        $blocked = app(ExaAiOperationsService::class)->safeResume(null, 'resume test');
        $this->assertFalse($blocked['resumed']);
        $this->assertSame('UNRESOLVED_CRITICAL_INCIDENT', $blocked['reason']);

        ExaAiOperationalIncident::query()->update(['status' => 'RESOLVED', 'resolved_at' => now()]);
        $resumed = app(ExaAiOperationsService::class)->safeResume(null, 'dependencies recovered');

        $this->assertTrue($resumed['resumed']);
        $this->assertSame('RESUMED', $resumed['reason']);
        $this->assertSame('NORMAL', ExaAiPublicSetting::query()->where('key', 'global_controls')->firstOrFail()->value['state']);
    }

    public function test_operations_load_probe_records_10k_portfolio_capacity(): void
    {
        $user = User::factory()->create();
        $now = now();
        $rows = [];
        for ($i = 0; $i < 10000; $i++) {
            $rows[] = [
                'user_id' => $user->id,
                'asset' => 'USDT',
                'mode' => 'live',
                'status' => 'active',
                'allocated_amount' => '100.00000000',
                'available_amount' => '100.00000000',
                'reserved_amount' => '0.00000000',
                'deployed_amount' => '0.00000000',
                'equity_amount' => '100.00000000',
                'realized_pnl' => '0.00000000',
                'unrealized_pnl' => '0.00000000',
                'high_water_mark' => '100.00000000',
                'risk_profile' => 'balanced',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) === 500) {
                ExaAiPortfolio::query()->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            ExaAiPortfolio::query()->insert($rows);
        }

        $run = app(ExaAiOperationsService::class)->recordPortfolioLoadProbe(10000);

        $this->assertSame('passed', $run->status);
        $this->assertSame(10000, (int) $run->metrics['portfolios_scanned']);
    }

    private function seedOperationalBaseline(array $marketOverrides = []): void
    {
        ExaAiPublicSetting::query()->create([
            'key' => 'redis_health',
            'value' => ['available' => true],
        ]);
        ExaAiPublicSetting::query()->create([
            'key' => 'queue_health',
            'value' => ['backlog' => 0],
        ]);
        ExaAiMarketEligibility::query()->create(array_merge([
            'symbol' => 'BTCUSDT',
            'product' => 'spot',
            'status' => 'enabled',
            'risk_tier' => 'standard',
            'min_liquidity' => '100000.00000000',
            'max_exposure' => '1000.00000000',
            'max_concentration_percent' => '20',
            'max_slippage_bps' => 50,
            'market_data_freshness_seconds' => 30,
            'metadata' => ['current_liquidity' => '100000.00000000', 'spread_bps' => 10],
        ], $marketOverrides));
    }
}
