<?php

declare(strict_types=1);

namespace App\Services\Custody;

use Illuminate\Support\Facades\DB;

class BlockchainNetworkHealthService
{
    public function __construct(private readonly BlockchainProviderManager $providers)
    {
    }

    public function refresh(?string $network = null): array
    {
        $query = DB::table('blockchain_networks');
        if ($network) {
            $query->where('network', strtolower($network));
        }

        $rows = [];
        foreach ($query->get() as $row) {
            $provider = $this->providers->providerFor((string) $row->network);
            $providerState = $provider->providerState((string) $row->network);
            $state = $providerState === 'HEALTHY' ? (string) $row->state : 'DEGRADED';
            DB::table('blockchain_networks')->where('id', $row->id)->update([
                'state' => $state,
                'last_health_checked_at' => now(),
                'updated_at' => now(),
            ]);
            $rows[] = array_merge((array) $row, ['provider_state' => $providerState, 'computed_state' => $state]);
        }

        return $rows;
    }
}
