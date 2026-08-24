<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\SecurityIncident;
use Illuminate\Support\Str;
use RuntimeException;

class SecurityIncidentService
{
    public function create(string $category, string $severity, string $scopeType = 'GLOBAL', ?string $scopeReference = null, array $impact = []): SecurityIncident
    {
        return SecurityIncident::query()->create([
            'incident_uuid' => (string) Str::uuid(),
            'category' => strtoupper($category),
            'severity' => strtoupper($severity),
            'status' => 'DETECTED',
            'scope_type' => strtoupper($scopeType),
            'scope_reference' => $scopeReference,
            'timeline' => [['status' => 'DETECTED', 'at' => now()->toISOString()]],
            'impact' => $impact,
            'corrective_actions' => [],
        ]);
    }

    public function transition(SecurityIncident $incident, string $status, ?Admin $admin = null, ?string $note = null): SecurityIncident
    {
        $allowed = ['DETECTED', 'ACKNOWLEDGED', 'CONTAINING', 'INVESTIGATING', 'RECOVERING', 'MONITORING', 'RESOLVED', 'POSTMORTEM'];
        if (! in_array(strtoupper($status), $allowed, true)) {
            throw new RuntimeException('Unsupported security incident state.');
        }

        $timeline = $incident->timeline ?? [];
        $timeline[] = ['status' => strtoupper($status), 'at' => now()->toISOString(), 'admin_id' => $admin?->id, 'note' => $note];
        $incident->forceFill([
            'status' => strtoupper($status),
            'timeline' => $timeline,
            'owner_admin_id' => $admin?->id ?? $incident->owner_admin_id,
            'resolved_at' => strtoupper($status) === 'RESOLVED' ? now() : $incident->resolved_at,
        ])->save();

        return $incident->fresh();
    }
}
