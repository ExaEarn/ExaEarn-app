<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class PolkadotStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'DOT';
    }

    public function network(): string
    {
        return 'polkadot';
    }

    protected function requiredCapabilities(): array
    {
        return ['bonding', 'nomination_pools', 'era_tracking', 'reward_claiming', 'commission', 'unbonding_chunks', 'slashing_exposure'];
    }
}
