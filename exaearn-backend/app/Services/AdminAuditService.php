<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AdminAuditService
{
    public function log(?Admin $admin, string $action, array $data = [], ?Request $request = null): void
    {
        $request ??= request();

        AdminLog::query()->create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'data' => array_merge($data, [
                'ip' => $request?->ip(),
                'device' => $request?->userAgent(),
            ]),
        ]);

        AuditLog::query()->create([
            'user_id' => null,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'device' => $request?->userAgent(),
            'metadata' => array_merge($data, [
                'admin_id' => $admin?->id,
                'admin_email' => $admin?->email,
                'event_source' => 'admin_api',
            ]),
            'created_at' => now(),
        ]);
    }
}
