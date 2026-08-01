<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class NearStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'NEAR';
    }

    public function network(): string
    {
        return 'near';
    }

    protected function requiredCapabilities(): array
    {
        return ['staking_pool_contract_calls', 'deposit_and_stake', 'epoch_tracking', 'reward_retrieval', 'unstake_waiting_period', 'withdraw_available'];
    }
}
