<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityIncident;
use App\Models\SreHealthSnapshot;
use App\Models\SreQueueState;
use App\Models\SreService;
use App\Models\SreWorkerHeartbeat;
use Illuminate\Support\Str;

class ReliabilityOperationsService
{
    public function __construct(
        private readonly SreServiceRegistry $registry,
        private readonly SreDependencyHealthService $dependencies,
        private readonly SreQueueReliabilityService $queues,
        private readonly SreObservabilityService $observability,
    ) {
    }

    public function evaluate(): array
    {
        $this->registry->seedCore();
        $db = $this->dependencies->checkDatabase();
        $queue = $this->queues->inspectDatabaseQueue('default');
        $observability = $this->observability->readiness();
        $services = SreService::query()->get();
        $deadWorkers = SreWorkerHeartbeat::query()->where('status', 'DEAD')->count();
        $activeIncidents = SecurityIncident::query()->whereNotIn('status', ['RESOLVED', 'POSTMORTEM'])->count();
        $reasons = [];

        if ($db->status !== 'PASS') {
            $reasons[] = 'DATABASE_UNAVAILABLE';
        }
        if ($queue->status === 'CRITICAL') {
            $reasons[] = 'QUEUE_BACKLOG_CRITICAL';
        }
        if ($deadWorkers > 0) {
            $reasons[] = 'WORKER_DEAD';
        }
        if ($activeIncidents > 0) {
            $reasons[] = 'ACTIVE_SECURITY_INCIDENT';
        }

        $status = match (true) {
            in_array('DATABASE_UNAVAILABLE', $reasons, true) => 'MAJOR_OUTAGE',
            in_array('QUEUE_BACKLOG_CRITICAL', $reasons, true) => 'PARTIAL_OUTAGE',
            $reasons !== [] => 'DEGRADED',
            default => 'HEALTHY',
        };

        $payload = [
            'status' => $status,
            'reason_codes' => $reasons,
            'services' => $services->pluck('status', 'service_id')->all(),
            'liveness' => ['api' => 'PASS'],
            'readiness' => ['database' => $db->status, 'queue' => $queue->status],
            'dependency_health' => $this->dependencies->latestByDependency(),
            'business_readiness' => ['finance' => 'READY', 'security' => $activeIncidents > 0 ? 'DEGRADED' : 'READY'],
            'observability' => $observability,
        ];

        SreHealthSnapshot::query()->create([
            'snapshot_uuid' => (string) Str::uuid(),
            'scope' => 'GLOBAL',
            'overall_status' => $status,
            'liveness' => $payload['liveness'],
            'readiness' => $payload['readiness'],
            'dependency_health' => array_map(fn ($check) => $check->toArray(), $payload['dependency_health']),
            'business_readiness' => $payload['business_readiness'],
            'reason_codes' => $reasons,
            'impact' => ['safe_mode_required' => $status !== 'HEALTHY'],
            'captured_at' => now(),
        ]);

        return $payload;
    }
}
