<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\GiftCard\GiftCardAdminCenterService;

class AccountClosureSafetyService
{
    public function __construct(private readonly GiftCardAdminCenterService $giftcards)
    {
    }

    public function readiness(int $userId): array
    {
        $blockers = [
            ...$this->giftcards->closureBlockers($userId),
        ];

        return [
            'can_close' => $blockers === [],
            'status' => $blockers === [] ? 'PASS' : 'BLOCKED',
            'blockers' => $blockers,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function assertCanClose(int $userId): void
    {
        $readiness = $this->readiness($userId);
        if (!$readiness['can_close']) {
            throw new \RuntimeException('Account closure is blocked by unresolved product activity.');
        }
    }
}
