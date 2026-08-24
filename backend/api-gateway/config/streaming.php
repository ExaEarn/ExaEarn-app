<?php

declare(strict_types=1);

return [
    'driver' => env('STREAMING_DRIVER', 'redis'),
    'sse_enabled' => filter_var(env('STREAMING_SSE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'price_channel' => env('STREAMING_PRICE_CHANNEL', 'price_updates'),
    'portfolio_channel' => env('STREAMING_PORTFOLIO_CHANNEL', 'portfolio_updates'),
    'market_channel' => env('STREAMING_MARKET_CHANNEL', 'exaearn.market.stream'),
    'margin_channel' => env('STREAMING_MARGIN_CHANNEL', 'margin_updates'),

    'node' => [
        'enabled' => filter_var(env('STREAMING_NODE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'url' => env('NODE_SERVICE_URL', ''),
        'secret' => env('NODE_SERVICE_SECRET', ''),
        'timeout_seconds' => (float) env('NODE_SERVICE_TIMEOUT', 0.5),
    ],
];
