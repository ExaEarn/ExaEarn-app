<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

enum OfflineInstrumentState: string
{
    case ISSUED = 'issued';
    case PENDING_ACKNOWLEDGMENT = 'pending_acknowledgment';
    case ACKNOWLEDGED = 'acknowledged';
    case RECONCILIATION_PENDING = 'reconciliation_pending';
    case REDEEMED = 'redeemed';
    case FAILED = 'failed';
    case RECOVERY_REQUIRED = 'recovery_required';
    case EXPIRED = 'expired';
    case SUSPENDED = 'suspended';
}
