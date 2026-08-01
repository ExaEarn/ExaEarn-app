<?php

declare(strict_types=1);
use App\Domain\Staking\Providers\AvalancheStakingProvider;
use App\Domain\Staking\Providers\BnbStakingProvider;
use App\Domain\Staking\Providers\CardanoStakingProvider;
use App\Domain\Staking\Providers\CosmosStakingProvider;
use App\Domain\Staking\Providers\EthereumStakingProvider;
use App\Domain\Staking\Providers\NearStakingProvider;
use App\Domain\Staking\Providers\PolkadotStakingProvider;
use App\Domain\Staking\Providers\PolygonStakingProvider;
use App\Domain\Staking\Providers\SolanaStakingProvider;
use App\Domain\Staking\Providers\SuiStakingProvider;
use App\Domain\Staking\Providers\TezosStakingProvider;

return [
    'reward_token' => env('STAKING_REWARD_TOKEN', 'EXA'),
    'environment' => env('STAKING_NETWORK_ENVIRONMENT', 'testnet'),
    'exatoken_asset' => env('EXATOKEN_ASSET', 'EXA'),
    'legacy_xrp_frozen' => true,
    'seconds_per_year' => 31536000,
    'min_reward_payout' => env('STAKING_MIN_REWARD_PAYOUT', '0.00000001'),
    'supported_native_pos_assets' => [
        'SOL', 'ETH', 'ADA', 'BNB', 'AVAX', 'SUI', 'DOT', 'ATOM', 'NEAR', 'XTZ', 'POL',
    ],
    'excluded_native_pos_assets' => [
        'XRP', 'BTC', 'USDT', 'USDC', 'PI',
    ],
    'providers' => [
        'SOL' => SolanaStakingProvider::class,
        'ETH' => EthereumStakingProvider::class,
        'ADA' => CardanoStakingProvider::class,
        'BNB' => BnbStakingProvider::class,
        'AVAX' => AvalancheStakingProvider::class,
        'SUI' => SuiStakingProvider::class,
        'DOT' => PolkadotStakingProvider::class,
        'ATOM' => CosmosStakingProvider::class,
        'NEAR' => NearStakingProvider::class,
        'XTZ' => TezosStakingProvider::class,
        'POL' => PolygonStakingProvider::class,
    ],
];
