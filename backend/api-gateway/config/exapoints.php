<?php

return [
    'redis_channel' => env('EXAPOINT_REDIS_CHANNEL', 'exapoint_updates'),
    'balance_cache_ttl' => (int) env('EXAPOINT_BALANCE_CACHE_TTL', 60),
    'rate_limit_per_minute' => (int) env('EXAPOINT_RATE_LIMIT_PER_MINUTE', 120),
    'large_adjustment_threshold' => env('EXAPOINT_LARGE_ADJUSTMENT_THRESHOLD', '10000'),
    'conversion' => [
        'default_rate' => env('EXAPOINT_CONVERSION_RATE', '1000'),
    ],
];

