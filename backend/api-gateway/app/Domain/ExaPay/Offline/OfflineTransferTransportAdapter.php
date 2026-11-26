<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

/** BLE and QR transports must preserve the same frame identity, ordering, and digest semantics. */
interface OfflineTransferTransportAdapter
{
    /** @return list<OfflineTransportFrame> */
    public function fragment(string $transferUuid, string $canonicalMessage, int $maxFrameBytes): array;

    /** @param list<OfflineTransportFrame> $frames */
    public function reassemble(array $frames): string;
}
