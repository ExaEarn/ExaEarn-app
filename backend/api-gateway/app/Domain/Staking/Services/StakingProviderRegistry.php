<?php

declare(strict_types=1);

namespace App\Domain\Staking\Services;

use App\Domain\Staking\Contracts\StakingProviderInterface;
use App\Domain\Staking\Exceptions\StakingProviderNotReadyException;

class StakingProviderRegistry
{
    public function forSymbol(string $symbol): StakingProviderInterface
    {
        $symbol = strtoupper($symbol);
        $class = config("staking.providers.{$symbol}");

        if (! $class || ! class_exists($class)) {
            throw new StakingProviderNotReadyException("No native PoS staking provider is registered for {$symbol}.");
        }

        return app($class);
    }
}
