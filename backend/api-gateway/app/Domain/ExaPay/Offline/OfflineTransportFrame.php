<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

final readonly class OfflineTransportFrame
{
    public function __construct(public string $transferUuid, public int $sequence, public int $total, public string $digest, public string $payload)
    {
        if ($sequence < 0 || $total < 1 || $sequence >= $total || strlen($digest) !== 64) {
            throw new \InvalidArgumentException('Offline transport frame metadata is invalid.');
        }
    }
}
