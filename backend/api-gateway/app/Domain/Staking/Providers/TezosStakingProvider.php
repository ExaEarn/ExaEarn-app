<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class TezosStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'XTZ';
    }

    public function network(): string
    {
        return 'tezos';
    }

    protected function requiredCapabilities(): array
    {
        return ['baker_selection', 'delegation', 'cycle_tracking', 'reward_maturity', 'baker_commission', 'external_reward_settlement_verification'];
    }
}
