<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\SecurityEmergencyControl;
use Illuminate\Support\Str;

class SecurityEmergencyControlService
{
    public function activate(Admin $admin, string $type, string $scopeType, ?string $scopeReference, string $reason, array $metadata = []): SecurityEmergencyControl
    {
        return SecurityEmergencyControl::query()->create([
            'control_uuid' => (string) Str::uuid(),
            'control_type' => strtoupper($type),
            'scope_type' => strtoupper($scopeType),
            'scope_reference' => $scopeReference,
            'status' => 'ACTIVE',
            'reason' => $reason,
            'metadata' => array_merge(['risk_reduction_preserved' => true], $metadata),
            'activated_by_admin_id' => $admin->id,
            'activated_at' => now(),
        ]);
    }

    public function deactivate(Admin $admin, SecurityEmergencyControl $control, string $reason): SecurityEmergencyControl
    {
        $control->forceFill([
            'status' => 'INACTIVE',
            'metadata' => array_merge($control->metadata ?? [], ['deactivation_reason' => $reason]),
            'deactivated_by_admin_id' => $admin->id,
            'deactivated_at' => now(),
        ])->save();

        return $control->fresh();
    }
}
