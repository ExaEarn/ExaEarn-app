<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SreQueueState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SreQueueReliabilityService
{
    public function classify(string $queue): string
    {
        return match ($queue) {
            'settlement', 'withdrawals', 'custody', 'finance' => 'FINANCIAL_CRITICAL',
            'risk', 'security' => 'RISK_CRITICAL',
            'notifications' => 'NOTIFICATION',
            default => 'OPERATIONAL',
        };
    }

    public function record(string $queue, int $depth, int $oldestAgeSeconds, int $failedJobs = 0, array $metadata = []): SreQueueState
    {
        $status = $depth > 1000 || $oldestAgeSeconds > 600 || $failedJobs > 20 ? 'CRITICAL' : ($depth > 100 || $oldestAgeSeconds > 120 || $failedJobs > 0 ? 'WARNING' : 'HEALTHY');

        return SreQueueState::query()->create([
            'queue_uuid' => (string) Str::uuid(),
            'queue_name' => $queue,
            'classification' => $this->classify($queue),
            'depth' => $depth,
            'oldest_job_age_seconds' => $oldestAgeSeconds,
            'failed_jobs' => $failedJobs,
            'status' => $status,
            'metadata' => $metadata,
            'checked_at' => now(),
        ]);
    }

    public function inspectDatabaseQueue(string $queue = 'default'): SreQueueState
    {
        $depth = DB::getSchemaBuilder()->hasTable('jobs') ? (int) DB::table('jobs')->where('queue', $queue)->count() : 0;
        $oldest = 0;
        if ($depth > 0) {
            $oldestTimestamp = (int) DB::table('jobs')->where('queue', $queue)->min('created_at');
            $oldest = max(0, now()->timestamp - $oldestTimestamp);
        }
        $failed = DB::getSchemaBuilder()->hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        return $this->record($queue, $depth, $oldest, $failed, ['driver' => config('queue.default')]);
    }
}
