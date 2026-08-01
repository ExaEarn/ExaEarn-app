<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class AvalancheStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'AVAX';
    }

    public function network(): string
    {
        return 'avalanche';
    }

    protected function requiredCapabilities(): array
    {
        return ['validator_or_delegated_staking', 'minimum_amount', 'fixed_duration', 'start_end_times', 'reward_settlement_at_completion', 'principal_release'];
    }
}
