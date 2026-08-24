<?php

declare(strict_types=1);

return [
    'routing' => [
        'default_mode' => env('LIQUIDITY_ROUTING_MODE', 'INTERNAL_FIRST_WITH_BEST_EXECUTION_GUARD'),
        'max_split_count' => (int) env('LIQUIDITY_MAX_SPLIT_COUNT', 4),
        'default_max_slippage_bps' => env('LIQUIDITY_DEFAULT_MAX_SLIPPAGE_BPS', '50'),
        'stale_quote_seconds' => (int) env('LIQUIDITY_STALE_QUOTE_SECONDS', 10),
        'min_quality_score' => env('LIQUIDITY_MIN_QUALITY_SCORE', '60'),
    ],

    'external_venues' => [
        'binance' => [
            'enabled' => (bool) env('BINANCE_LIQUIDITY_ENABLED', false),
            'environment' => env('BINANCE_LIQUIDITY_ENV', 'UNCONFIGURED'),
            'base_url' => env('BINANCE_BASE_URL', env('BINANCE_API_URL', 'https://api.binance.com')),
            'api_key_ref' => env('BINANCE_API_KEY_REF'),
            'secret_ref' => env('BINANCE_SECRET_REF'),
            'withdrawals_enabled' => false,
        ],
    ],

    'treasury' => [
        'reserve_formula_version' => env('LIQUIDITY_WITHDRAWAL_RESERVE_VERSION', 'phase8-withdrawal-reserve-v1'),
        'default_minimum_reserve' => env('LIQUIDITY_DEFAULT_MINIMUM_RESERVE', '0'),
        'default_target_multiplier' => env('LIQUIDITY_TARGET_RESERVE_MULTIPLIER', '1.50'),
        'default_stress_multiplier' => env('LIQUIDITY_STRESS_RESERVE_MULTIPLIER', '3.00'),
    ],

    'market_making' => [
        'enabled' => (bool) env('EXAEARN_MARKET_MAKING_ENABLED', false),
        'quote_ttl_seconds' => (int) env('EXAEARN_MARKET_MAKING_QUOTE_TTL_SECONDS', 15),
        'default_spread_bps' => env('EXAEARN_MARKET_MAKING_DEFAULT_SPREAD_BPS', '20'),
        'max_inventory_usage_bps' => env('EXAEARN_MARKET_MAKING_MAX_INVENTORY_USAGE_BPS', '5000'),
    ],
];
