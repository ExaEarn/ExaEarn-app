<?php

declare(strict_types=1);

return [
    'enabled' => env('PRICING_ENGINE_ENABLED', true),
    'shadow_mode' => env('PRICING_ENGINE_SHADOW_MODE', true),
    'enforced_products' => array_filter(array_map('trim', explode(',', (string) env(
        'PRICING_ENFORCED_PRODUCTS',
        ''
    )))),
    'quote_ttl_seconds' => (int) env('PRICING_QUOTE_TTL_SECONDS', 30),
    'cache_ttl_seconds' => (int) env('PRICING_RULE_CACHE_TTL_SECONDS', 60),

    'guardrails' => [
        'max_percentage_bps' => env('PRICING_MAX_PERCENTAGE_BPS', '1000'),
        'max_spread_bps' => env('PRICING_MAX_SPREAD_BPS', '1000'),
        'max_fixed_fee' => env('PRICING_MAX_FIXED_FEE', '1000000'),
        'negative_fee_requires_explicit_rebate' => true,
    ],

    'precedence' => [
        'USER_CONTRACT' => 1000,
        'INSTITUTION_CONTRACT' => 900,
        'PROMOTION' => 800,
        'VIP' => 700,
        'MERCHANT_TIER' => 600,
        'COUNTRY' => 500,
        'PRODUCT_DEFAULT' => 100,
    ],

    'products' => [
        'SPOT',
        'FUTURES',
        'MARGIN',
        'CONVERT',
        'WITHDRAWAL',
        'FIAT',
        'P2P',
        'STAKING',
        'COPY_TRADING',
        'EXAAI',
        'GIFTCARD',
        'NFT',
        'OTC',
        'MARKET_MAKER',
        'INSTITUTIONAL',
        'LISTING',
    ],

    'reward_daily_cap_default' => env('PRICING_REWARD_DAILY_CAP_DEFAULT', '100000'),
    'reward_budget_warning_ratio' => env('PRICING_REWARD_BUDGET_WARNING_RATIO', '0.80'),
];
