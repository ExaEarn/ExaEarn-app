<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

enum OfflineReconciliationClaimState: string
{
    case SUBMITTED = 'SUBMITTED';
    case VALIDATING = 'VALIDATING';
    case EVIDENCE_INCOMPLETE = 'EVIDENCE_INCOMPLETE';
    case CONFLICT_DETECTED = 'CONFLICT_DETECTED';
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case REDEMPTION_PENDING = 'REDEMPTION_PENDING';
    case REDEEMED = 'REDEEMED';
    case REJECTED = 'REJECTED';
    case RECOVERY_REQUIRED = 'RECOVERY_REQUIRED';

    public function mayTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::SUBMITTED => [self::VALIDATING, self::CONFLICT_DETECTED, self::REJECTED],
            self::VALIDATING => [self::EVIDENCE_INCOMPLETE, self::CONFLICT_DETECTED, self::PENDING, self::VERIFIED, self::REJECTED],
            self::EVIDENCE_INCOMPLETE => [self::VALIDATING, self::CONFLICT_DETECTED, self::REJECTED],
            self::PENDING => [self::VALIDATING, self::VERIFIED, self::CONFLICT_DETECTED, self::REJECTED],
            self::VERIFIED => [self::REDEMPTION_PENDING, self::CONFLICT_DETECTED],
            self::REDEMPTION_PENDING => [self::REDEEMED, self::RECOVERY_REQUIRED, self::CONFLICT_DETECTED],
            self::RECOVERY_REQUIRED => [self::REDEMPTION_PENDING, self::REDEEMED, self::CONFLICT_DETECTED],
            self::CONFLICT_DETECTED, self::REDEEMED, self::REJECTED => [],
        }, true);
    }
}
