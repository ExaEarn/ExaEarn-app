<?php

declare(strict_types=1);

return [
    'mode' => env('MARGIN_TRADING_MODE', 'disabled'), // disabled, internal, shadow, enabled, halted

    'health' => [
        'initial_borrow_min' => env('MARGIN_INITIAL_HEALTH_MIN', '1.250000000000000000'),
        'warning' => env('MARGIN_HEALTH_WARNING', '1.150000000000000000'),
        'borrow_disabled' => env('MARGIN_HEALTH_BORROW_DISABLED', '1.050000000000000000'),
        'liquidation' => env('MARGIN_HEALTH_LIQUIDATION', '1.000000000000000000'),
    ],

    'reference_currency' => env('MARGIN_REFERENCE_CURRENCY', 'USDT'),

    'reference_prices' => [
        'USDT' => '1',
        'USDC' => '1',
        'BTC' => env('MARGIN_REF_PRICE_BTC', '60000'),
        'ETH' => env('MARGIN_REF_PRICE_ETH', '3000'),
        'SOL' => env('MARGIN_REF_PRICE_SOL', '150'),
        'XRP' => env('MARGIN_REF_PRICE_XRP', '0.6'),
    ],

    'seconds_per_year' => '31536000',

    'required_liquidity' => [
        'USDT' => env('MARGIN_REQUIRED_LIQUIDITY_USDT', '1'),
        'USDC' => env('MARGIN_REQUIRED_LIQUIDITY_USDC', '1'),
        'BTC' => env('MARGIN_REQUIRED_LIQUIDITY_BTC', '0.00000001'),
        'ETH' => env('MARGIN_REQUIRED_LIQUIDITY_ETH', '0.00000001'),
    ],
];
