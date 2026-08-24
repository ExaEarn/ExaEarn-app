<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class RpcFailoverService
{
    public function select(array $providers, string $expectedNetwork): array
    {
        foreach ($providers as $provider) {
            if (($provider['status'] ?? 'DOWN') !== 'HEALTHY') {
                continue;
            }
            if (($provider['network'] ?? null) !== $expectedNetwork) {
                throw new RuntimeException('RPC wrong-chain protection rejected provider.');
            }
            if ((int) ($provider['block_lag'] ?? 999999) > 5) {
                continue;
            }

            return ['status' => 'SELECTED', 'provider' => $provider['name'], 'network' => $provider['network']];
        }

        return ['status' => 'PAUSE', 'reason' => 'NO_VALID_RPC_PROVIDER'];
    }
}
