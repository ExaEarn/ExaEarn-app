<?php

declare(strict_types=1);

namespace App\Domain\Staking\Contracts;

interface StakingProviderInterface
{
    public function assetSymbol(): string;

    public function network(): string;

    public function healthCheck(): array;

    public function discoverValidators(): array;

    public function estimateFees(string $amount): array;

    public function buildDelegationTransaction(array $delegation): array;

    public function submitSignedTransaction(string $signedPayload, array $metadata = []): array;

    public function monitorConfirmation(string $transactionHash): array;

    public function verifyDelegation(array $delegation): array;

    public function discoverRewards(array $delegation, array $period): array;

    public function buildUndelegationTransaction(array $unstakeRequest): array;

    public function verifyPrincipalWithdrawable(array $unstakeRequest): array;

    public function detectSlashing(array $delegation): array;
}
