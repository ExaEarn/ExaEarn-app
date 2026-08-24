<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\CardAuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class CardAuditService
{
    public function record(?User $user, string $action, ?string $resourceType = null, ?int $resourceId = null, array $metadata = [], ?User $actor = null): CardAuditLog
    {
        return CardAuditLog::query()->create([
            'audit_uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'actor_type' => $actor ? 'USER' : 'SYSTEM',
            'actor_id' => $actor?->id,
            'action' => strtoupper($action),
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
        ]);
    }
}
