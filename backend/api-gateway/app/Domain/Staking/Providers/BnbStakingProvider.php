<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class BnbStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'BNB';
    }

    public function network(): string
    {
        return 'bnb-smart-chain';
    }

    protected function requiredCapabilities(): array
    {
        return ['validator_selection', 'delegation', 'minimum_delegation', 'reward_collection', 'redelegation_rules', 'undelegation', 'unbonding', 'jail_detection'];
    }
}
