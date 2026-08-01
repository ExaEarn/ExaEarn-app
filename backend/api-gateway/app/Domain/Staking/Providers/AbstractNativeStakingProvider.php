<?php

declare(strict_types=1);

namespace App\Domain\Staking\Providers;

use App\Domain\Staking\Contracts\StakingProviderInterface;
use App\Domain\Staking\Exceptions\StakingProviderNotReadyException;

abstract class AbstractNativeStakingProvider implements StakingProviderInterface
{
    abstract public function assetSymbol(): string;

    abstract public function network(): string;

    abstract protected function requiredCapabilities(): array;

    public function healthCheck(): array
    {
        $rpcEndpoints = array_values(array_filter([
            config('services.staking_rpc.'.strtolower($this->assetSymbol()).'.primary'),
            config('services.staking_rpc.'.strtolower($this->assetSymbol()).'.secondary'),
        ]));

        return [
            'asset' => $this->assetSymbol(),
            'network' => $this->network(),
            'ready' => false,
            'status' => 'configuration_required',
            'rpc_endpoints_configured' => count($rpcEndpoints),
            'capabilities' => $this->requiredCapabilities(),
        ];
    }

    public function discoverValidators(): array
    {
        $this->notReady('validator discovery requires configured RPC/provider credentials');
    }

    public function estimateFees(string $amount): array
    {
        $this->notReady('fee estimation requires configured RPC/provider credentials');
    }

    public function buildDelegationTransaction(array $delegation): array
    {
        $this->notReady('delegation transaction creation requires a configured chain adapter and secure signer');
    }

    public function submitSignedTransaction(string $signedPayload, array $metadata = []): array
    {
        $this->notReady('transaction broadcasting requires configured RPC/provider credentials');
    }

    public function monitorConfirmation(string $transactionHash): array
    {
        $this->notReady('confirmation monitoring requires configured RPC/provider credentials');
    }

    public function verifyDelegation(array $delegation): array
    {
        $this->notReady('delegation verification requires configured RPC/provider credentials');
    }

    public function discoverRewards(array $delegation, array $period): array
    {
        $this->notReady('native reward discovery requires configured RPC/provider credentials');
    }

    public function buildUndelegationTransaction(array $unstakeRequest): array
    {
        $this->notReady('undelegation transaction creation requires configured chain adapter and secure signer');
    }

    public function verifyPrincipalWithdrawable(array $unstakeRequest): array
    {
        $this->notReady('principal release requires verified network withdrawable state');
    }

    public function detectSlashing(array $delegation): array
    {
        $this->notReady('slashing detection requires configured RPC/provider credentials');
    }

    protected function notReady(string $reason): never
    {
        throw new StakingProviderNotReadyException($this->assetSymbol().' staking provider is not production-ready: '.$reason);
    }
}
