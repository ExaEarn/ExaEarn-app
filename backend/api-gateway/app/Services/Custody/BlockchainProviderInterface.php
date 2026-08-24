<?php

declare(strict_types=1);

namespace App\Services\Custody;

interface BlockchainProviderInterface extends BlockchainNetworkInterface
{
    public function providerId(): string;

    public function providerState(string $network): string;
}
