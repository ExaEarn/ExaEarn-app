<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperApiKey;
use App\Models\User;

class ApiSecurityService
{
    public function __construct(
        private readonly SecuritySignalService $signals,
        private readonly SecurityCaseService $cases,
    ) {
    }

    public function recordApiAbuse(User $user, string $type, array $metadata = []): void
    {
        $this->signals->record($type, 'DEVELOPER_API', 'USER', $user->id, 'HIGH', $metadata, '0.9500', 3600);
    }

    public function respondToCompromisedKey(DeveloperApiKey $key, string $reason): array
    {
        $key->forceFill(['status' => 'revoked'])->save();
        $case = $this->cases->create('API_COMPROMISE', 'HIGH', 'USER', $key->user_id, [
            'api_key_id' => $key->id,
            'reason' => $reason,
            'safe_actions_preserved' => ['cancel_orders', 'reduce_risk'],
        ]);

        return ['key_status' => $key->fresh()->status, 'case_uuid' => $case->case_uuid];
    }
}
