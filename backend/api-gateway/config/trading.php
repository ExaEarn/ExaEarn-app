<?php

declare(strict_types=1);

return [
    'engine' => [
        'mode' => env('TRADING_ENGINE_MODE', 'legacy'),
        'default_mode' => env('TRADING_ENGINE_DEFAULT_MODE', env('TRADING_ENGINE_MODE', 'legacy')),
        'market_overrides' => array_filter(array_map('trim', explode(',', env('TRADING_ENGINE_MARKET_OVERRIDES', '')))),
        'instance_id' => env('SPOT_ENGINE_INSTANCE_ID', env('APP_NAME', 'exaearn') . '-' . gethostname()),
        'lease_ttl_seconds' => env('SPOT_ENGINE_LEASE_TTL_SECONDS', 30),
        'settlement_max_attempts' => env('SPOT_ENGINE_SETTLEMENT_MAX_ATTEMPTS', 5),
        'settlement_pending_halt_threshold' => env('SPOT_ENGINE_SETTLEMENT_PENDING_HALT_THRESHOLD', 1000),
    ],
    'fee_collector_user_id' => env('TRADE_FEE_COLLECTOR_USER_ID'),
    'maker_fee' => env('SPOT_MAKER_FEE_RATE', '0.001'),
    'taker_fee' => env('SPOT_TAKER_FEE_RATE', '0.002'),
    'market_order_slippage_bps' => env('SPOT_MARKET_ORDER_SLIPPAGE_BPS', '100'),
    'market_order_protection_bps' => env('SPOT_MARKET_ORDER_PROTECTION_BPS', '500'),
    'liquidity' => [
        'default_mode' => env('SPOT_LIQUIDITY_DEFAULT_MODE', 'INTERNAL_ONLY'),
        'default_price_authority' => env('SPOT_PRICE_AUTHORITY_DEFAULT_MODE', 'REFERENCE_ASSISTED'),
        'external_routing_enabled' => filter_var(env('SPOT_EXTERNAL_ROUTING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'market_overrides' => array_filter(array_map('trim', explode(',', env('SPOT_LIQUIDITY_MARKET_OVERRIDES', '')))),
        'max_external_percent_per_order' => env('SPOT_MAX_EXTERNAL_PERCENT_PER_ORDER', '0'),
        'max_external_notional' => env('SPOT_MAX_EXTERNAL_NOTIONAL', '0'),
        'max_slippage_bps' => env('SPOT_EXTERNAL_MAX_SLIPPAGE_BPS', '50'),
        'shadow_only' => filter_var(env('SPOT_EXTERNAL_ROUTING_SHADOW_ONLY', true), FILTER_VALIDATE_BOOL),
    ],
    'stream' => [
        'driver' => env('TRADE_STREAM_DRIVER', 'redis'),
        'channel' => env('TRADE_STREAM_CHANNEL', 'exaearn.market.stream'),
        'fallback_to_http' => filter_var(env('TRADE_STREAM_FALLBACK_TO_HTTP', true), FILTER_VALIDATE_BOOL),
    ],
];
