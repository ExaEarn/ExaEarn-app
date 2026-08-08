<?php

declare(strict_types=1);

return [
    'driver' => env('STREAMING_DRIVER', 'redis'),

    'price_channel' => env('STREAMING_PRICE_CHANNEL', 'price_updates'),
    'portfolio_channel' => env('STREAMING_PORTFOLIO_CHANNEL', 'portfolio_updates'),

    'node' => [
        'enabled' => filter_var(env('STREAMING_NODE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'url' => env('NODE_SERVICE_URL', ''),
        'secret' => env('NODE_SERVICE_SECRET', ''),
        'timeout_seconds' => (float) env('NODE_SERVICE_TIMEOUT', 0.5),
    ],
];
