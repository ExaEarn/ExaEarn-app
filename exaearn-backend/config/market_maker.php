<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('MARKET_MAKER_ENABLED', true), FILTER_VALIDATE_BOOL),
    'system_user_id' => (int) env('MARKET_MAKER_SYSTEM_USER_ID', 1),
    'anchor' => [
        'source' => env('MARKET_ANCHOR_SOURCE', 'binance'),
        'max_deviation_percent' => (float) env('MARKET_ANCHOR_MAX_DEVIATION_PERCENT', 3.0),
    ],
    'spread' => [
        'min_percent' => (float) env('MARKET_SPREAD_MIN_PERCENT', 0.2),
        'max_percent' => (float) env('MARKET_SPREAD_MAX_PERCENT', 2.5),
    ],
    'risk' => [
        'max_notional_per_market' => (float) env('MARKET_MAKER_MAX_NOTIONAL_PER_MARKET', 500000),
        'pool_usage_limit_percent' => (float) env('MARKET_MAKER_POOL_USAGE_LIMIT_PERCENT', 80),
        'min_depth_levels' => (int) env('MARKET_MAKER_MIN_DEPTH_LEVELS', 2),
    ],
];
