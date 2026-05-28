<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(?int $userId, string $action, ?Request $request = null, array $metadata = []): void
    {
        $request ??= request();

        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'device' => (string) $request?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

