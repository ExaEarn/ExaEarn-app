<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

class SolanaStakingProvider extends AbstractNativeStakingProvider
{
    public function assetSymbol(): string
    {
        return 'SOL';
    }

    public function network(): string
    {
        return 'solana';
    }

    protected function requiredCapabilities(): array
    {
        return ['stake_account_creation', 'stake_authority', 'withdraw_authority', 'vote_account_selection', 'delegation', 'epoch_activation', 'epoch_rewards', 'deactivation', 'withdrawal', 'delinquency_monitoring', 'rpc_failover'];
    }
}
