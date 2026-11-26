<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

use App\Models\LedgerTransaction;

/** Implement only after the counterparty and consumption proof protocol is independently verified. */
interface OfflineRedemptionContract
{
    public function redeem(VerifiedOfflineConsumptionClaim $claim, string $idempotencyKey): LedgerTransaction;
}
