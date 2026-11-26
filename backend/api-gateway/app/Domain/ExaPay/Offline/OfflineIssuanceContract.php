<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

final readonly class OfflineIssuanceContract
{
    public function __construct(
        public OfflinePaymentMode $mode,
        public string $deviceUuid,
        public int $amountMinor,
        public string $asset,
        public string $idempotencyKey,
    ) {
        if ($mode !== OfflinePaymentMode::OFFLINE_STORED_VALUE || $asset !== 'SIMUSDT' || $amountMinor < 1 || $idempotencyKey === '') {
            throw new \InvalidArgumentException('Offline issuance requires a positive SIMUSDT amount and an idempotency key.');
        }
    }
}
