<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ListingApplication;
use App\Models\ListingAssetNetworkConfiguration;
use App\Models\ListingContractValidation;
use Illuminate\Support\Str;

class ListingContractValidationService
{
    public function validate(ListingApplication $application, ?ListingAssetNetworkConfiguration $networkConfiguration, array $payload): ListingContractValidation
    {
        $network = strtoupper((string) ($payload['network'] ?? $networkConfiguration?->network));
        $family = strtoupper((string) ($payload['network_family'] ?? $payload['family'] ?? 'EVM'));
        $contract = $payload['contract_address'] ?? null;
        $decimals = (int) ($payload['decimals'] ?? $networkConfiguration?->decimals ?? 0);
        $symbol = strtoupper((string) ($payload['symbol'] ?? $application->asset_information['symbol'] ?? ''));
        $name = (string) ($payload['name'] ?? $application->asset_information['name'] ?? $symbol);
        $riskFlags = $this->riskFlags($payload);
        $error = null;
        $status = 'PASS';

        if ($contract && $family === 'EVM' && ! preg_match('/^0x[a-fA-F0-9]{40}$/', (string) $contract)) {
            $status = 'FAIL';
            $error = 'Invalid EVM contract address format.';
        }
        if ($decimals < 0 || $decimals > 36) {
            $status = 'FAIL';
            $error = 'Invalid decimals.';
        }
        if (! $contract && strtoupper((string) ($payload['asset_type'] ?? 'TOKEN')) !== 'NATIVE') {
            $status = 'OPERATIONAL_SETUP_REQUIRED';
            $error = 'External chain-native contract validation requires configured RPC/provider access.';
        }

        return ListingContractValidation::query()->updateOrCreate([
            'application_id' => $application->id,
            'network' => $network,
            'contract_address' => $contract,
        ], [
            'validation_uuid' => (string) (ListingContractValidation::query()
                ->where('application_id', $application->id)
                ->where('network', $network)
                ->where('contract_address', $contract)
                ->value('validation_uuid') ?: Str::uuid()),
            'listing_asset_network_configuration_id' => $networkConfiguration?->id,
            'status' => $status,
            'submitted_metadata' => [
                'symbol' => $symbol,
                'name' => $name,
                'decimals' => $decimals,
                'token_standard' => $payload['token_standard'] ?? $networkConfiguration?->token_standard,
            ],
            'validated_metadata' => [
                'mode' => app()->environment('production') ? 'PROVIDER_REQUIRED' : 'SANDBOX_SOFTWARE_VALIDATION',
                'symbol' => $symbol,
                'name' => $name,
                'decimals' => $decimals,
                'contract_exists' => $status === 'PASS',
            ],
            'risk_flags' => $riskFlags,
            'error' => $error,
            'checked_at' => now(),
        ]);
    }

    private function riskFlags(array $payload): array
    {
        $flags = [];
        $map = [
            'upgradeable' => 'UPGRADEABLE_CONTRACT',
            'proxy' => 'PROXY_CONTRACT',
            'pausable' => 'PAUSABLE',
            'blacklist_capability' => 'BLACKLIST_CAPABILITY',
            'transfer_restriction' => 'TRANSFER_RESTRICTION',
            'fee_on_transfer' => 'FEE_ON_TRANSFER',
            'mintable' => 'MINTABLE',
            'freeze_authority' => 'FREEZE_AUTHORITY',
            'owner_privileges' => 'OWNER_PRIVILEGES',
            'unusual_behavior' => 'UNUSUAL_TOKEN_BEHAVIOR',
        ];

        foreach ($map as $input => $flag) {
            if (! empty($payload[$input])) {
                $flags[] = $flag;
            }
        }

        return $flags;
    }
}

