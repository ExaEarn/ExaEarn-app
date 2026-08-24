<?php

declare(strict_types=1);

return [
    'price_feed_max_age_ms' => env('TRADING_PRICE_FEED_MAX_AGE_MS', 30000),
    'max_order_price_deviation_bps' => env('TRADING_MAX_ORDER_PRICE_DEVIATION_BPS', 1000),
    'maximum_spread_bps' => env('TRADING_MAX_SPREAD_BPS', 500),
    'max_index_deviation_bps' => env('TRADING_MAX_INDEX_DEVIATION_BPS', 750),
    'max_mark_price_deviation_bps' => env('TRADING_MAX_MARK_PRICE_DEVIATION_BPS', 750),
    'volatility_guard_threshold_bps' => env('TRADING_VOLATILITY_GUARD_BPS', 2500),
    'default_max_order_notional' => env('TRADING_DEFAULT_MAX_ORDER_NOTIONAL', '1000000'),
    'default_max_position_notional' => env('TRADING_DEFAULT_MAX_POSITION_NOTIONAL', '5000000'),
    'default_max_leverage' => env('TRADING_DEFAULT_MAX_LEVERAGE', 20),
    'lending_pool_high_utilization_bps' => env('TRADING_LENDING_POOL_HIGH_UTILIZATION_BPS', 9000),
    'queue_backlog_warning' => env('TRADING_QUEUE_BACKLOG_WARNING', 1000),
    'load_probe_iterations' => env('TRADING_LOAD_PROBE_ITERATIONS', 25),
];
