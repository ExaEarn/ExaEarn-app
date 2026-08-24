<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SreBackupRecord;
use App\Models\SreDependencyCheck;
use App\Models\SreHealthSnapshot;
use App\Models\SreOperationalAlert;
use App\Models\SreQueueState;
use App\Models\SreRecoveryAction;
use App\Models\SreService;
use App\Models\SreSloDefinition;
use App\Models\SreWorkerHeartbeat;
use App\Services\ProductionConfigValidationService;
use App\Services\ReliabilityOperationsService;
use App\Services\SreObservabilityService;
use App\Services\SreBackupService;
use App\Services\SreDependencyHealthService;
use App\Services\SreQueueReliabilityService;
use App\Services\SreRecoveryService;
use App\Services\SreServiceRegistry;
use App\Services\SreWorkerSupervisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReliabilityOperationsController extends Controller
{
    public function __construct(
        private readonly ReliabilityOperationsService $operations,
        private readonly SreServiceRegistry $registry,
        private readonly SreDependencyHealthService $dependencies,
        private readonly SreQueueReliabilityService $queues,
        private readonly SreWorkerSupervisorService $workers,
        private readonly SreBackupService $backups,
        private readonly SreRecoveryService $recovery,
        private readonly ProductionConfigValidationService $config,
        private readonly SreObservabilityService $observability,
    ) {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'health' => $this->operations->evaluate(),
            'latest_snapshot' => SreHealthSnapshot::query()->latest('captured_at')->first(),
            'services' => SreService::query()->orderBy('criticality')->orderBy('service_id')->get(),
            'queues' => SreQueueState::query()->latest('checked_at')->limit(20)->get(),
            'workers' => SreWorkerHeartbeat::query()->latest('last_heartbeat_at')->limit(20)->get(),
            'backups' => SreBackupRecord::query()->latest()->limit(10)->get(),
            'recovery_actions' => SreRecoveryAction::query()->latest()->limit(10)->get(),
            'observability' => $this->observability->readiness(),
        ]]);
    }

    public function services(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $this->admin($request, 'operations.config.manage');
            $data = $request->validate([
                'service_id' => ['required', 'string', 'max:120'],
                'service_name' => ['required', 'string', 'max:160'],
                'service_type' => ['required', 'string', 'max:80'],
                'criticality' => ['required', Rule::in(['TIER_0', 'TIER_1', 'TIER_2', 'TIER_3'])],
                'environment' => ['nullable', 'string', 'max:32'],
                'version' => ['nullable', 'string', 'max:80'],
                'deployment_id' => ['nullable', 'string', 'max:120'],
                'region' => ['nullable', 'string', 'max:80'],
                'dependencies' => ['nullable', 'array'],
                'health_endpoint' => ['nullable', 'string', 'max:255'],
                'readiness_endpoint' => ['nullable', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'max:32'],
                'metadata' => ['nullable', 'array'],
            ]);

            return response()->json(['data' => $this->registry->register($data)], 201);
        }

        return response()->json(['data' => $this->registry->all()]);
    }

    public function dependencies(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $this->admin($request, 'operations.config.manage');
            $data = $request->validate([
                'service_id' => ['required', 'string', 'max:120'],
                'dependency' => ['required', 'string', 'max:120'],
                'dependency_type' => ['required', 'string', 'max:80'],
                'status' => ['required', Rule::in(['PASS', 'FAIL', 'DEGRADED', 'UNKNOWN'])],
                'latency_ms' => ['nullable', 'integer', 'min:0'],
                'evidence' => ['nullable', 'array'],
            ]);

            return response()->json(['data' => $this->dependencies->check(
                $data['service_id'],
                $data['dependency'],
                $data['dependency_type'],
                $data['status'],
                $data['evidence'] ?? [],
                $data['latency_ms'] ?? null,
            )], 201);
        }

        return response()->json(['data' => [
            'graph' => $this->dependencies->graph(),
            'latest' => SreDependencyCheck::query()->latest('checked_at')->limit(50)->get(),
        ]]);
    }

    public function queues(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $this->admin($request, 'operations.config.manage');
            $data = $request->validate([
                'queue_name' => ['required', 'string', 'max:120'],
                'depth' => ['required', 'integer', 'min:0'],
                'oldest_job_age_seconds' => ['required', 'integer', 'min:0'],
                'failed_jobs' => ['nullable', 'integer', 'min:0'],
                'metadata' => ['nullable', 'array'],
            ]);

            return response()->json(['data' => $this->queues->record(
                $data['queue_name'],
                $data['depth'],
                $data['oldest_job_age_seconds'],
                $data['failed_jobs'] ?? 0,
                $data['metadata'] ?? [],
            )], 201);
        }

        return response()->json(['data' => SreQueueState::query()->latest('checked_at')->paginate((int) $request->query('per_page', 50))]);
    }

    public function workers(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'worker_id' => ['required', 'string', 'max:160'],
                'worker_type' => ['required', 'string', 'max:80'],
                'queue_name' => ['nullable', 'string', 'max:120'],
                'metadata' => ['nullable', 'array'],
            ]);

            return response()->json(['data' => $this->workers->heartbeat($data['worker_id'], $data['worker_type'], $data['queue_name'] ?? null, $data['metadata'] ?? [])], 201);
        }

        $this->workers->detectDead();

        return response()->json(['data' => SreWorkerHeartbeat::query()->latest('last_heartbeat_at')->paginate((int) $request->query('per_page', 50))]);
    }

    public function backups(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $this->admin($request, 'operations.backup.manage');
            $data = $request->validate([
                'backup_type' => ['required', Rule::in(['DATABASE', 'REDIS', 'OBJECT_STORAGE', 'CONFIG', 'FULL'])],
                'scope' => ['required', 'string', 'max:80'],
                'status' => ['required', Rule::in(['STARTED', 'COMPLETED', 'FAILED', 'VERIFYING'])],
                'metadata' => ['nullable', 'array'],
            ]);

            return response()->json(['data' => $this->backups->record($data['backup_type'], $data['scope'], $data['status'], $data['metadata'] ?? [])], 201);
        }

        return response()->json(['data' => SreBackupRecord::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function alerts(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $this->admin($request, 'operations.incident.manage');
            $data = $request->validate([
                'alert_key' => ['required', 'string', 'max:160'],
                'severity' => ['required', Rule::in(['INFO', 'WARNING', 'HIGH', 'CRITICAL'])],
                'service_id' => ['nullable', 'string', 'max:120'],
                'component' => ['nullable', 'string', 'max:120'],
                'evidence' => ['nullable', 'array'],
            ]);

            return response()->json(['data' => $this->observability->triggerAlert(
                $data['alert_key'],
                $data['severity'],
                $data['evidence'] ?? [],
                $data['service_id'] ?? null,
                $data['component'] ?? null,
            )], 201);
        }

        return response()->json(['data' => SreOperationalAlert::query()->latest('last_triggered_at')->paginate((int) $request->query('per_page', 50))]);
    }

    public function acknowledgeAlert(Request $request, string $uuid): JsonResponse
    {
        $this->admin($request, 'operations.incident.manage');
        $alert = SreOperationalAlert::query()->where('alert_uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->observability->acknowledge($alert)]);
    }

    public function resolveAlert(Request $request, string $uuid): JsonResponse
    {
        $this->admin($request, 'operations.incident.manage');
        $data = $request->validate(['metadata' => ['nullable', 'array']]);
        $alert = SreOperationalAlert::query()->where('alert_uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->observability->resolve($alert, $data['metadata'] ?? [])]);
    }

    public function slos(): JsonResponse
    {
        $this->observability->seedCoreSloDefinitions();

        return response()->json(['data' => SreSloDefinition::query()->orderBy('service_id')->orderBy('sli_key')->get()]);
    }

    public function markRestoreTested(Request $request, string $uuid): JsonResponse
    {
        $this->admin($request, 'operations.backup.restore');
        $data = $request->validate([
            'status' => ['required', Rule::in(['PASS', 'FAIL', 'PARTIAL'])],
            'result' => ['nullable', 'array'],
        ]);

        $backup = SreBackupRecord::query()->where('backup_uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->backups->markRestoreTested($backup, $data['status'], $data['result'] ?? [])]);
    }

    public function recovery(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $admin = $this->admin($request, 'operations.recovery.request');
            $data = $request->validate([
                'action_type' => ['required', 'string', 'max:80'],
                'scope' => ['required', 'string', 'max:80'],
                'scope_reference' => ['nullable', 'string', 'max:180'],
                'reason' => ['required', 'string', 'min:8', 'max:2000'],
            ]);

            return response()->json(['data' => $this->recovery->request($admin, $data['action_type'], $data['scope'], $data['scope_reference'] ?? null, $data['reason'])], 202);
        }

        return response()->json(['data' => SreRecoveryAction::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function approveRecovery(Request $request, string $uuid): JsonResponse
    {
        $admin = $this->admin($request, 'operations.recovery.approve');
        $action = SreRecoveryAction::query()->where('action_uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->recovery->approve($admin, $action)]);
    }

    public function executeRecovery(Request $request, string $uuid): JsonResponse
    {
        $this->admin($request, 'operations.recovery.execute');
        $action = SreRecoveryAction::query()->where('action_uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->recovery->execute($action)]);
    }

    public function configValidation(Request $request): JsonResponse
    {
        $environment = $request->query('environment');

        return response()->json(['data' => $this->config->validate(is_string($environment) ? $environment : null)]);
    }

    private function admin(Request $request, string $permission): Admin
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->hasPermission($permission), 403);

        return $admin;
    }
}
