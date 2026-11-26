<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

enum OfflinePaymentLifecycleState: string
{
    case CREATED = 'created';
    case AUTHORIZED = 'authorized';
    case ISSUED = 'issued';
    case OFFLINE_COMMITTED = 'offline_committed';
    case RECONCILIATION_PENDING = 'reconciliation_pending';
    case REDEMPTION_PENDING = 'redemption_pending';
    case REDEEMED = 'redeemed';
    case FAILED = 'failed';
    case RECOVERY_REQUIRED = 'recovery_required';
}
