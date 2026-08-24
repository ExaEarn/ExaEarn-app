<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginDevice;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class SessionSecurityService
{
    public function __construct(private readonly SecuritySignalService $signals)
    {
    }

    public function activeSessions(User $user): array
    {
        return $user->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'risk_state' => 'NORMAL',
        ])->all();
    }

    public function revokeSession(User $user, int $tokenId): bool
    {
        return $user->tokens()->whereKey($tokenId)->delete() > 0;
    }

    public function revokeOtherSessions(User $user, ?int $currentTokenId): int
    {
        return $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();
    }

    public function markDevice(User $user, string $fingerprint, string $state, array $metadata = []): LoginDevice
    {
        $device = $user->loginDevices()->updateOrCreate([
            'fingerprint_hash' => hash('sha256', $fingerprint),
        ], [
            'device_name' => $metadata['device_name'] ?? 'Unknown device',
            'ip_address' => $metadata['ip_address'] ?? '127.0.0.1',
            'user_agent' => $metadata['user_agent'] ?? null,
            'last_login_at' => now(),
        ]);

        if (in_array(strtoupper($state), ['NEW', 'REVIEW_REQUIRED', 'BLOCKED'], true)) {
            $this->signals->record('DEVICE_'.strtoupper($state), 'DEVICE', 'USER', $user->id, strtoupper($state) === 'BLOCKED' ? 'HIGH' : 'MEDIUM', [
                'fingerprint_hash' => $device->fingerprint_hash,
                'device_name' => $device->device_name,
                'ip_address' => $device->ip_address,
            ], '0.8500', 86400);
        }

        return $device;
    }
}
