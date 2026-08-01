<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class EthereumStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'ETH';
    }

    public function network(): string
    {
        return 'ethereum';
    }

    protected function requiredCapabilities(): array
    {
        return ['approved_provider_or_direct_validators', 'deposit_contract', 'withdrawal_credentials', 'activation_queue', 'consensus_rewards', 'execution_rewards', 'exits', 'partial_withdrawals', 'slashing_detection'];
    }
}
