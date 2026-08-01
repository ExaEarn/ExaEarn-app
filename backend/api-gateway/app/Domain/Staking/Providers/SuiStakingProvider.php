<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class SuiStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'SUI';
    }

    public function network(): string
    {
        return 'sui';
    }

    protected function requiredCapabilities(): array
    {
        return ['validator_pool_selection', 'staked_sui_object_creation', 'epoch_activation', 'exchange_rate_tracking', 'unstaking', 'object_reconciliation'];
    }
}
