<?php

declare(strict_types=1);

return [
    'driver' => env('STREAMING_DRIVER', 'redis'),

    'price_channel' => env('STREAMING_PRICE_CHANNEL', 'price_updates'),
    'portfolio_channel' => env('STREAMING_PORTFOLIO_CHANNEL', 'portfolio_updates'),

    'node' => [
        'url' => env('NODE_SERVICE_URL', 'http://localhost:4000'),
        'secret' => env('NODE_SERVICE_SECRET', ''),
        'timeout_seconds' => (int) env('NODE_SERVICE_TIMEOUT', 15),
    ],
];
