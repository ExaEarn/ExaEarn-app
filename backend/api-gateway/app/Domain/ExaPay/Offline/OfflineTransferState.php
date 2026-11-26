<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

enum OfflineTransferState: string
{
    case PREPARED = 'prepared';
    case COMMITTED = 'committed';
    case ACKNOWLEDGED = 'acknowledged';
    case CANCELLED = 'cancelled';
    case RECOVERY_REQUIRED = 'recovery_required';
    case QUARANTINED = 'quarantined';

    public function mayTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PREPARED => in_array($next, [self::COMMITTED, self::CANCELLED, self::RECOVERY_REQUIRED], true),
            self::COMMITTED => in_array($next, [self::ACKNOWLEDGED, self::RECOVERY_REQUIRED, self::QUARANTINED], true),
            self::RECOVERY_REQUIRED => in_array($next, [self::COMMITTED, self::ACKNOWLEDGED, self::QUARANTINED], true),
            self::ACKNOWLEDGED => $next === self::QUARANTINED,
            self::CANCELLED, self::QUARANTINED => false,
        };
    }
}
