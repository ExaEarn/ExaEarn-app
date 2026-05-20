<?php

declare(strict_types=1);

return [
    'fee_collector_user_id' => env('TRADE_FEE_COLLECTOR_USER_ID'),
    'stream' => [
        'driver' => env('TRADE_STREAM_DRIVER', 'redis'),
        'channel' => env('TRADE_STREAM_CHANNEL', 'exaearn.market.stream'),
        'fallback_to_http' => filter_var(env('TRADE_STREAM_FALLBACK_TO_HTTP', true), FILTER_VALIDATE_BOOL),
    ],
];
