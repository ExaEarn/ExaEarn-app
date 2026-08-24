<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SreWorkerHeartbeat;
use Illuminate\Support\Str;

class SreWorkerSupervisorService
{
    public function heartbeat(string $workerId, string $type, ?string $queue = null, array $metadata = []): SreWorkerHeartbeat
    {
        return SreWorkerHeartbeat::query()->updateOrCreate([
            'worker_id' => $workerId,
        ], [
            'worker_uuid' => (string) (SreWorkerHeartbeat::query()->where('worker_id', $workerId)->value('worker_uuid') ?: Str::uuid()),
            'worker_type' => strtoupper($type),
            'queue_name' => $queue,
            'version' => config('app.version'),
            'started_at' => SreWorkerHeartbeat::query()->where('worker_id', $workerId)->value('started_at') ?: now(),
            'last_heartbeat_at' => now(),
            'last_job_at' => $metadata['last_job_at'] ?? null,
            'failure_count' => $metadata['failure_count'] ?? 0,
            'status' => 'HEALTHY',
            'metadata' => $metadata,
        ]);
    }

    public function detectDead(int $staleSeconds = 120): array
    {
        SreWorkerHeartbeat::query()
            ->where('last_heartbeat_at', '<', now()->subSeconds($staleSeconds))
            ->where('status', 'HEALTHY')
            ->update(['status' => 'DEAD']);

        return SreWorkerHeartbeat::query()->where('status', 'DEAD')->get()->all();
    }
}
