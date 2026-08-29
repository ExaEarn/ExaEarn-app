<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\GiftCard\GiftCardAdminCenterService;
use App\Services\AgriTech\AgriAccountClosureService;

class AccountClosureSafetyService
{
    public function __construct(
        private readonly GiftCardAdminCenterService $giftcards,
        private readonly AgriAccountClosureService $agritech,
        private readonly FlightGameAccountClosureService $flightGames,
        private readonly AffiliateCommissionService $affiliates,
        private readonly CrowdfundingService $crowdfunding,
        private readonly ExaSkillsService $exaSkills,
    ) {
    }

    public function readiness(int $userId): array
    {
        $blockers = [
            ...$this->giftcards->closureBlockers($userId),
            ...$this->agritech->blockers($userId),
            ...$this->flightGames->blockers($userId),
            ...$this->affiliates->accountClosureBlockers($userId),
            ...$this->crowdfunding->assertNoClosureBlockers($userId),
            ...$this->exaSkills->accountClosureBlockers($userId),
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
