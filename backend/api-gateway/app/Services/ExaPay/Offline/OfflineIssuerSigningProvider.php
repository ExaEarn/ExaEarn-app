<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

interface OfflineIssuerSigningProvider
{
    public function sign(array $payload): array;
    public function publicKey(): ?string;
}
