<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('SOR_ENABLED', true), FILTER_VALIDATE_BOOL),
    'default_max_slippage_percent' => (float) env('SOR_DEFAULT_MAX_SLIPPAGE_PERCENT', 0.5),
    'external_timeout_ms' => (int) env('SOR_EXTERNAL_TIMEOUT_MS', 4000),
    'external_retry' => (int) env('SOR_EXTERNAL_RETRY', 2),
    'external_fee_percent' => (float) env('SOR_EXTERNAL_FEE_PERCENT', 0.1),
];
