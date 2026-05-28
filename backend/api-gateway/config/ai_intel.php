<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('AI_INTEL_ENABLED', true), FILTER_VALIDATE_BOOL),
    'python_service_url' => env('AI_INTEL_PYTHON_URL', 'http://127.0.0.1:9001'),
    'symbols' => explode(',', (string) env('AI_INTEL_SYMBOLS', 'BTCUSDT,ETHUSDT,XRPUSDT')),
    'safety' => [
        'min_spread' => (float) env('AI_INTEL_MIN_SPREAD', 0.0005),
        'max_spread' => (float) env('AI_INTEL_MAX_SPREAD', 0.02),
        'min_order_size' => (float) env('AI_INTEL_MIN_ORDER_SIZE', 10),
        'max_order_size' => (float) env('AI_INTEL_MAX_ORDER_SIZE', 50000),
    ],
];
