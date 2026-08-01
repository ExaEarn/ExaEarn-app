<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class PolygonStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'POL';
    }

    public function network(): string
    {
        return 'polygon';
    }

    protected function requiredCapabilities(): array
    {
        return ['stake_manager_contract', 'validator_selection', 'reward_claiming', 'unbonding', 'withdraw_claim', 'checkpoint_tracking', 'pol_token_handling', 'contract_event_indexing'];
    }
}
