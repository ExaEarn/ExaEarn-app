<?php

return [
    'enabled' => env('FUTURES_ENABLED', true),
    'min_leverage' => (int) env('FUTURES_MIN_LEVERAGE', 1),
    'max_leverage' => (int) env('FUTURES_MAX_LEVERAGE', 100),
    'max_position_notional' => env('FUTURES_MAX_POSITION_NOTIONAL', '1000000'),
    'order_rate_limit_per_minute' => (int) env('FUTURES_ORDER_RATE_LIMIT', 120),
    'maintenance_margin_buffer' => env('FUTURES_MAINTENANCE_MARGIN_BUFFER', '0.0025'),
    'funding_interval_hours' => (int) env('FUTURES_FUNDING_INTERVAL_HOURS', 8),
    'engine_mode' => env('FUTURES_ENGINE_MODE', 'legacy'),
    'allow_external_execution' => filter_var(env('FUTURES_ALLOW_EXTERNAL_EXECUTION', false), FILTER_VALIDATE_BOOL),
    'default_risk_tiers' => [
        ['notional_floor' => '0', 'notional_cap' => '50000', 'maintenance_margin_rate' => '0.005', 'maintenance_amount' => '0', 'max_leverage' => 100],
        ['notional_floor' => '50000', 'notional_cap' => '250000', 'maintenance_margin_rate' => '0.01', 'maintenance_amount' => '250', 'max_leverage' => 50],
        ['notional_floor' => '250000', 'notional_cap' => '1000000', 'maintenance_margin_rate' => '0.025', 'maintenance_amount' => '4000', 'max_leverage' => 20],
    ],
    'index' => [
        'min_healthy_constituents' => (int) env('FUTURES_INDEX_MIN_HEALTHY_CONSTITUENTS', 1),
        'max_constituent_age_seconds' => (int) env('FUTURES_INDEX_MAX_CONSTITUENT_AGE_SECONDS', 10),
        'max_constituent_deviation_bps' => (int) env('FUTURES_INDEX_MAX_DEVIATION_BPS', 300),
    ],
    'mark' => [
        'max_premium_rate' => env('FUTURES_MARK_MAX_PREMIUM_RATE', '0.01'),
    ],
    'funding' => [
        'interest_rate' => env('FUTURES_FUNDING_INTEREST_RATE', '0.0001'),
        'max_rate' => env('FUTURES_FUNDING_MAX_RATE', '0.0075'),
        'min_rate' => env('FUTURES_FUNDING_MIN_RATE', '-0.0075'),
    ],
    'liquidation' => [
        'fee_rate' => env('FUTURES_LIQUIDATION_FEE_RATE', '0.005'),
        'partial_liquidation_ratio' => env('FUTURES_PARTIAL_LIQUIDATION_RATIO', '0.50'),
        'max_stages' => (int) env('FUTURES_LIQUIDATION_MAX_STAGES', 4),
    ],
    'cross' => [
        'warning_ratio' => env('FUTURES_CROSS_WARNING_RATIO', '1.25'),
        'recovery_ratio' => env('FUTURES_CROSS_RECOVERY_RATIO', '1.50'),
    ],
    'stream_channel' => env('FUTURES_STREAM_CHANNEL', 'futures_updates'),
    'copy_max_allocation' => env('FUTURES_COPY_MAX_ALLOCATION', '50000'),
    'copy_risk_multiplier' => [
        'low' => '0.50',
        'medium' => '1.00',
        'high' => '1.50',
    ],
];
