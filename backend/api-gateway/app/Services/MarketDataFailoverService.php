<?php

declare(strict_types=1);

namespace App\Services;

class MarketDataFailoverService
{
    public function select(array $sources, string $maxDeviationBps = '100'): array
    {
        $primary = $sources[0] ?? null;
        if ($primary && ($primary['status'] ?? null) === 'FRESH') {
            return ['status' => 'PRIMARY', 'source' => $primary['name']];
        }

        foreach (array_slice($sources, 1) as $source) {
            if (($source['status'] ?? null) !== 'FRESH') {
                continue;
            }
            if (bccomp((string) ($source['deviation_bps'] ?? '999999'), $maxDeviationBps, 8) === 1) {
                continue;
            }

            return ['status' => 'FAILOVER', 'source' => $source['name']];
        }

        return ['status' => 'STALE_PROTECTION', 'action' => 'DISABLE_NEW_RISK'];
    }
}
