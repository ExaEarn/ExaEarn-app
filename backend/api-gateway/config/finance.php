<?php

declare(strict_types=1);

return [
    'reporting_currency' => env('FINANCE_REPORTING_CURRENCY', 'USD'),
    'policy_version' => env('FINANCE_POLICY_VERSION', 'phase17-v1'),
    'backing' => [
        'warning_ratio' => env('FINANCE_BACKING_WARNING_RATIO', '1.05'),
        'critical_ratio' => env('FINANCE_BACKING_CRITICAL_RATIO', '1.00'),
        'freshness_minutes' => env('FINANCE_BACKING_FRESHNESS_MINUTES', 60),
    ],
];
