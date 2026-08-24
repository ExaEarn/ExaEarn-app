<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityIncident;
use App\Models\SreBackupRecord;
use App\Models\SreHealthSnapshot;
use App\Models\SreOperationalAlert;
use App\Models\SreQueueState;
use App\Models\SreRecoveryAction;
use App\Models\SreService;
use App\Models\SreSloDefinition;
use App\Models\SreWorkerHeartbeat;
use App\Services\MarketDataFailoverService;
use App\Services\PermissionService;
use App\Services\ProductionConfigValidationService;
use App\Services\ReliabilityOperationsService;
use App\Services\RpcFailoverService;
use App\Services\SecurityIncidentService;
use App\Services\SreBackupService;
use App\Services\SreDependencyHealthService;
use App\Services\SreObservabilityService;
use App\Services\SreQueueReliabilityService;
use App\Services\SreRecoveryService;
use App\Services\SreServiceRegistry;
use App\Services\SreWorkerSupervisorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class Phase19ReliabilitySreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
    }

    public function test_service_registry_dependency_graph_liveness_readiness_and_business_health_are_persisted(): void
    {
        app(SreServiceRegistry::class)->seedCore();
        app(SreDependencyHealthService::class)->check('spot-engine', 'redis', 'CACHE', 'PASS', ['role' => 'realtime'], 7);

        $health = app(ReliabilityOperationsService::class)->evaluate();
        $graph = app(SreDependencyHealthService::class)->graph();

        $this->assertSame('HEALTHY', $health['status']);
        $this->assertSame('PASS', $health['readiness']['database']);
        $this->assertArrayHasKey('spot-engine', $graph);
        $this->assertContains('postgresql', $graph['spot-engine']);
        $this->assertSame('READY', $health['observability']['status']);
        $this->assertGreaterThanOrEqual(8, SreService::query()->count());
        $this->assertGreaterThanOrEqual(7, SreSloDefinition::query()->count());
        $this->assertTrue(SreHealthSnapshot::query()->where('overall_status', 'HEALTHY')->exists());
    }

    public function test_queue_worker_incident_and_config_guards_fail_closed_without_rewriting_business_systems(): void
    {
        app(SreQueueReliabilityService::class)->record('settlement', 1501, 701, 21, ['dlq' => true]);
        $worker = app(SreWorkerSupervisorService::class)->heartbeat('settlement-worker-1', 'SETTLEMENT', 'settlement');
        $worker->forceFill(['last_heartbeat_at' => now()->subMinutes(10), 'status' => 'HEALTHY'])->save();
        app(SreWorkerSupervisorService::class)->detectDead(120);
        app(SecurityIncidentService::class)->create('SRE_SECURITY_SIGNAL', 'SEV3', 'GLOBAL', null, ['phase' => 19]);

        $originalConnection = config('database.default');
        config(['database.default' => 'sqlite']);
        $config = app(ProductionConfigValidationService::class)->validate('production');
        config(['database.default' => $originalConnection]);

        $health = app(ReliabilityOperationsService::class)->evaluate();

        $this->assertSame('CRITICAL', SreQueueState::query()->where('queue_name', 'settlement')->latest()->firstOrFail()->status);
        $this->assertSame('DEAD', SreWorkerHeartbeat::query()->where('worker_id', 'settlement-worker-1')->firstOrFail()->status);
        $this->assertTrue(SecurityIncident::query()->whereNotIn('status', ['RESOLVED', 'POSTMORTEM'])->exists());
        $this->assertSame('FAIL', $config['status']);
        $this->assertContains('SQLITE_PRODUCTION_FORBIDDEN', $config['issues']);
        $this->assertContains($health['status'], ['DEGRADED', 'PARTIAL_OUTAGE']);
    }

    public function test_market_data_and_rpc_failover_preserve_source_separation_and_wrong_chain_protection(): void
    {
        $market = app(MarketDataFailoverService::class)->select([
            ['name' => 'exaearn-internal', 'status' => 'STALE'],
            ['name' => 'binance-reference', 'status' => 'FRESH', 'deviation_bps' => '42'],
        ], '100');
        $stale = app(MarketDataFailoverService::class)->select([
            ['name' => 'exaearn-internal', 'status' => 'STALE'],
            ['name' => 'provider-x', 'status' => 'FRESH', 'deviation_bps' => '900'],
        ], '100');
        $rpc = app(RpcFailoverService::class)->select([
            ['name' => 'rpc-a', 'status' => 'DOWN', 'network' => 'ethereum-mainnet', 'block_lag' => 0],
            ['name' => 'rpc-b', 'status' => 'HEALTHY', 'network' => 'ethereum-mainnet', 'block_lag' => 2],
        ], 'ethereum-mainnet');

        $this->assertSame('FAILOVER', $market['status']);
        $this->assertSame('binance-reference', $market['source']);
        $this->assertSame('STALE_PROTECTION', $stale['status']);
        $this->assertSame('DISABLE_NEW_RISK', $stale['action']);
        $this->assertSame('SELECTED', $rpc['status']);

        $this->expectException(RuntimeException::class);
        app(RpcFailoverService::class)->select([
            ['name' => 'wrong-chain', 'status' => 'HEALTHY', 'network' => 'polygon-mainnet', 'block_lag' => 0],
        ], 'ethereum-mainnet');
    }

    public function test_backups_restore_drill_records_and_recovery_actions_require_maker_checker(): void
    {
        $maker = $this->admin('sre-maker@example.com');
        $checker = $this->admin('sre-checker@example.com');
        $backup = app(SreBackupService::class)->record('DATABASE', 'POSTGRESQL', 'COMPLETED', [
            'storage_reference' => 's3://private-bucket/prod-backup.sql.enc',
            'checksum' => hash('sha256', 'backup'),
            'retention_days' => 90,
        ]);
        $tested = app(SreBackupService::class)->markRestoreTested($backup, 'PASS', ['target' => 'isolated-staging']);
        $action = app(SreRecoveryService::class)->request($maker, 'RESUME_AFTER_OUTAGE', 'GLOBAL', null, 'Restore service in safe mode after dependency recovery.');

        $this->assertNotNull($backup->storage_reference_hash);
        $this->assertArrayNotHasKey('storage_reference', $backup->metadata ?? []);
        $this->assertSame('PASS', $tested->restore_test_status);

        try {
            app(SreRecoveryService::class)->approve($maker, $action);
            $this->fail('Maker-checker recovery approval should reject same-admin approval.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('segregation', $exception->getMessage());
        }

        $approved = app(SreRecoveryService::class)->approve($checker, $action->fresh());
        $this->assertSame('APPROVED', $approved->status);
        $executed = app(SreRecoveryService::class)->execute($approved);

        $this->assertContains($executed->status, ['EXECUTED_SAFE_MODE', 'BLOCKED']);
        $this->assertSame(1, SreBackupRecord::query()->count());
        $this->assertSame(1, SreRecoveryAction::query()->count());
    }

    public function test_admin_reliability_routes_are_authorized_and_return_operational_state(): void
    {
        $admin = $this->admin('sre-admin@example.com');
        $alert = app(SreObservabilityService::class)->triggerAlert('queue.backlog.default', 'HIGH', ['depth' => 200], 'api-gateway', 'queue');
        app(SreObservabilityService::class)->triggerAlert('queue.backlog.default', 'HIGH', ['depth' => 300], 'api-gateway', 'queue');

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/v1/reliability/overview')
            ->assertOk()
            ->assertJsonPath('data.health.readiness.database', 'PASS');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/v1/reliability/services', [
            'service_id' => 'phase19-test-service',
            'service_name' => 'Phase 19 Test Service',
            'service_type' => 'API',
            'criticality' => 'TIER_2',
            'dependencies' => ['postgresql'],
            'status' => 'HEALTHY',
        ])->assertCreated()->assertJsonPath('data.service_id', 'phase19-test-service');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/v1/reliability/backups', [
            'backup_type' => 'DATABASE',
            'scope' => 'POSTGRESQL',
            'status' => 'COMPLETED',
            'metadata' => ['storage_reference' => 'private://phase19'],
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/v1/reliability/config-validation?environment=production')
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'environment', 'database_connection', 'queue_connection', 'issues']]);
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/v1/reliability/slos')
            ->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/v1/reliability/alerts/'.$alert->alert_uuid.'/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.status', 'ACKNOWLEDGED');
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/v1/reliability/alerts/'.$alert->alert_uuid.'/resolve', [
            'metadata' => ['resolution' => 'worker_scaled'],
        ])->assertOk()->assertJsonPath('data.status', 'RESOLVED');

        $this->assertSame(1, SreOperationalAlert::query()->count());
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $permissions = [
            'operations.view',
            'operations.config.manage',
            'operations.backup.manage',
            'operations.backup.restore',
            'operations.recovery.request',
            'operations.recovery.approve',
            'operations.recovery.execute',
            'operations.incident.manage',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }
        app(PermissionService::class)->syncRolePermissions($role, $permissions);

        return Admin::query()->create([
            'name' => 'SRE Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
