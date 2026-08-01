<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class CardanoStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'ADA';
    }

    public function network(): string
    {
        return 'cardano';
    }

    protected function requiredCapabilities(): array
    {
        return ['stake_address_registration', 'pool_discovery', 'delegation_certificates', 'epoch_tracking', 'reward_account_tracking', 'reward_withdrawal', 'saturation_monitoring'];
    }
}
