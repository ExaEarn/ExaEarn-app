<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TradingOperationalAuditLog;
use Illuminate\Support\Str;

class TradingOperationalAuditService
{
    public function record(
        string $action,
        ?string $scope = null,
        ?string $scopeKey = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?int $adminId = null,
        ?string $correlationId = null,
    ): TradingOperationalAuditLog {
        return TradingOperationalAuditLog::query()->create([
            'audit_id' => (string) Str::uuid(),
            'admin_id' => $adminId,
            'actor_type' => $adminId ? 'admin' : 'system',
            'action' => $action,
            'scope' => $scope,
            'scope_key' => $scopeKey,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $reason,
            'correlation_id' => $correlationId,
            'performed_at' => now(),
        ]);
    }
}
