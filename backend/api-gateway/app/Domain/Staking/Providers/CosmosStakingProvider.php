<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class CosmosStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'ATOM';
    }

    public function network(): string
    {
        return 'cosmos-hub';
    }

    protected function requiredCapabilities(): array
    {
        return ['validator_discovery', 'delegation', 'reward_withdrawal', 'redelegation_restrictions', 'unbonding_entries', 'jailed_status', 'tendermint_confirmation'];
    }
}
