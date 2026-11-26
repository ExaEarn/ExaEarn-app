<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

final readonly class VerifiedOfflineConsumptionClaim
{
    public function __construct(
        public string $claimUuid,
        public string $instrumentUuid,
        public int $amountMinor,
        public string $asset,
        public string $journalDigest,
        public string $accountingReference,
        public bool $lineageVerified,
        public bool $consumptionVerified,
        public bool $conflictFree,
    ) {
        if (!$lineageVerified || !$consumptionVerified || !$conflictFree || $amountMinor < 1 || $asset !== 'SIMUSDT' || $accountingReference === '') {
            throw new \InvalidArgumentException('A redemption claim must have verified lineage, consumption, conflict status and accounting reference.');
        }
    }
}
