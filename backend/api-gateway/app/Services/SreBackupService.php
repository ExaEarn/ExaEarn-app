<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SreBackupRecord;
use Illuminate\Support\Str;

class SreBackupService
{
    public function record(string $type, string $scope, string $status, array $metadata = []): SreBackupRecord
    {
        $reference = $metadata['storage_reference'] ?? null;

        return SreBackupRecord::query()->create([
            'backup_uuid' => (string) Str::uuid(),
            'backup_type' => strtoupper($type),
            'scope' => strtoupper($scope),
            'status' => strtoupper($status),
            'storage_reference_hash' => $reference ? hash('sha256', (string) $reference) : null,
            'checksum' => $metadata['checksum'] ?? hash('sha256', json_encode($metadata)),
            'encrypted' => (bool) ($metadata['encrypted'] ?? true),
            'started_at' => $metadata['started_at'] ?? now(),
            'completed_at' => strtoupper($status) === 'COMPLETED' ? now() : null,
            'retention_until' => now()->addDays((int) ($metadata['retention_days'] ?? 30)),
            'restore_test_status' => $metadata['restore_test_status'] ?? 'NOT_RUN',
            'restore_tested_at' => $metadata['restore_tested_at'] ?? null,
            'metadata' => array_diff_key($metadata, ['storage_reference' => true]),
        ]);
    }

    public function markRestoreTested(SreBackupRecord $backup, string $status, array $result = []): SreBackupRecord
    {
        $backup->forceFill([
            'restore_test_status' => strtoupper($status),
            'restore_tested_at' => now(),
            'metadata' => array_merge($backup->metadata ?? [], ['restore_result' => $result]),
        ])->save();

        return $backup->fresh();
    }
}
