<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\SecurityCase;
use Illuminate\Support\Str;
use RuntimeException;

class SecurityCaseService
{
    public function create(string $type, string $severity, ?string $subjectType, ?int $subjectId, array $evidence = [], ?Admin $admin = null): SecurityCase
    {
        return SecurityCase::query()->create([
            'case_uuid' => (string) Str::uuid(),
            'case_type' => strtoupper($type),
            'severity' => strtoupper($severity),
            'status' => 'OPEN',
            'subject_type' => $subjectType ? strtoupper($subjectType) : null,
            'subject_id' => $subjectId,
            'evidence' => $evidence,
            'created_by_admin_id' => $admin?->id,
        ]);
    }

    public function transition(SecurityCase $case, string $status, ?Admin $admin = null, ?string $resolution = null): SecurityCase
    {
        $allowed = ['OPEN', 'TRIAGE', 'INVESTIGATING', 'ACTION_REQUIRED', 'MONITORING', 'RESOLVED', 'CLOSED', 'FALSE_POSITIVE'];
        if (! in_array(strtoupper($status), $allowed, true)) {
            throw new RuntimeException('Unsupported security case state.');
        }

        $case->forceFill([
            'status' => strtoupper($status),
            'resolved_by_admin_id' => in_array(strtoupper($status), ['RESOLVED', 'CLOSED', 'FALSE_POSITIVE'], true) ? $admin?->id : $case->resolved_by_admin_id,
            'resolved_at' => in_array(strtoupper($status), ['RESOLVED', 'CLOSED', 'FALSE_POSITIVE'], true) ? now() : $case->resolved_at,
            'resolution' => $resolution ?? $case->resolution,
        ])->save();

        return $case->fresh();
    }
}
