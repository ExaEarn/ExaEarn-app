<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

enum OfflinePaymentMode: string
{
    case ONLINE_INTERNAL_TRANSFER = 'ONLINE_INTERNAL_TRANSFER';
    case OFFLINE_STORED_VALUE = 'OFFLINE_STORED_VALUE';
}
