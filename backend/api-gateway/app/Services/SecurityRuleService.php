<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\SecurityRule;
use Illuminate\Support\Str;
use RuntimeException;

class SecurityRuleService
{
    public function change(Admin $admin, string $key, array $configuration, string $reason, string $mode = 'SHADOW', ?Admin $approver = null): SecurityRule
    {
        if ($approver && (int) $approver->id === (int) $admin->id) {
            throw new RuntimeException('Security rule approval requires segregation of duties.');
        }

        $version = ((int) SecurityRule::query()->where('rule_key', $key)->max('version')) + 1;

        return SecurityRule::query()->create([
            'rule_uuid' => (string) Str::uuid(),
            'rule_key' => $key,
            'version' => $version,
            'mode' => strtoupper($mode),
            'action' => $configuration['action'] ?? 'MONITOR',
            'configuration' => $configuration,
            'reason' => $reason,
            'changed_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $approver?->id,
            'effective_at' => now(),
        ]);
    }
}
