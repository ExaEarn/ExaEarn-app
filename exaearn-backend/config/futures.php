<?php

return [
    'enabled' => env('FUTURES_ENABLED', true),
    'min_leverage' => (int) env('FUTURES_MIN_LEVERAGE', 1),
    'max_leverage' => (int) env('FUTURES_MAX_LEVERAGE', 100),
    'max_position_notional' => env('FUTURES_MAX_POSITION_NOTIONAL', '1000000'),
    'order_rate_limit_per_minute' => (int) env('FUTURES_ORDER_RATE_LIMIT', 120),
    'maintenance_margin_buffer' => env('FUTURES_MAINTENANCE_MARGIN_BUFFER', '0.0025'),
    'funding_interval_hours' => (int) env('FUTURES_FUNDING_INTERVAL_HOURS', 8),
    'stream_channel' => env('FUTURES_STREAM_CHANNEL', 'futures_updates'),
    'copy_max_allocation' => env('FUTURES_COPY_MAX_ALLOCATION', '50000'),
    'copy_risk_multiplier' => [
        'low' => '0.50',
        'medium' => '1.00',
        'high' => '1.50',
    ],
];

