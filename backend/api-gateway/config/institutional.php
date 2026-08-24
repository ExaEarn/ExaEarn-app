<?php

declare(strict_types=1);

return [
    'large_transfer_threshold' => [
        'USDT' => env('INSTITUTIONAL_LARGE_TRANSFER_THRESHOLD_USDT', '50000'),
        'USD' => env('INSTITUTIONAL_LARGE_TRANSFER_THRESHOLD_USD', '50000'),
    ],
    'rate_profiles' => [
        'RETAIL' => ['requests_per_minute' => 120, 'orders_per_second' => 5],
        'PROFESSIONAL' => ['requests_per_minute' => 300, 'orders_per_second' => 15],
        'VIP' => ['requests_per_minute' => 600, 'orders_per_second' => 30],
        'INSTITUTIONAL' => ['requests_per_minute' => 1200, 'orders_per_second' => 60],
        'MARKET_MAKER' => ['requests_per_minute' => 2400, 'orders_per_second' => 120],
    ],
];
