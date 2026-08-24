<?php

declare(strict_types=1);

namespace App\Services\Custody;

use Illuminate\Support\Facades\DB;

class CustodyOperationalReadinessService
{
    public function __construct(
        private readonly CustodyRegistryService $registry,
        private readonly BlockchainNetworkHealthService $health,
        private readonly NetworkFeeManagementService $networkFees,
    ) {
    }

    public function evaluate(): array
    {
        $this->registry->syncFromConfig();
        $health = $this->health->refresh();
        $signerProvider = (string) config('custody.signing.provider', 'development_local');
        $productionEnabled = (bool) config('custody.production_enabled', false);
        $productionSignerOk = !$productionEnabled || in_array($signerProvider, (array) config('custody.signing.production_allowed_providers', []), true);

        $networks = [];
        foreach (DB::table('blockchain_networks')->orderBy('network')->get() as $network) {
            $hotWallets = DB::table('custody_wallets')->where('network', $network->network)->where('classification', 'HOT')->where('status', 'ACTIVE')->count();
            $networkFees = $this->networkFees->status((string) $network->network);
            $ready = $network->state === 'OPERATIONAL'
                && $hotWallets > 0
                && $productionSignerOk
                && count($networkFees) > 0;

            $networks[] = [
                'network' => $network->network,
                'state' => $network->state,
                'readiness' => $ready ? 'READY' : ($network->state === 'CRITICAL' ? 'NOT_READY' : 'DEGRADED'),
                'hot_wallets' => $hotWallets,
                'network_fee_reserves' => count($networkFees),
            ];
        }

        return [
            'overall' => collect($networks)->every(fn ($row) => $row['readiness'] === 'READY') ? 'READY' : 'CODE_READY_OPERATIONAL_SETUP_REQUIRED',
            'production_enabled' => $productionEnabled,
            'signer_provider' => $signerProvider,
            'production_signer_ok' => $productionSignerOk,
            'network_health' => $health,
            'networks' => $networks,
        ];
    }
}
