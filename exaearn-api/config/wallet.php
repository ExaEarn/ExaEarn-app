<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported assets
    |--------------------------------------------------------------------------
    |
    | Each asset defines:
    | - code: internal currency code (stored in DB)
    | - network: chain identifier used by Node microservice
    | - decimals: display / normalization hints
    |
    | NOTE:
    | - EXA/USDC/USDT are assumed to be Base (EVM) ERC-20 tokens.
    | - XRP is assumed to be native XRP Ledger.
    |
    */
    'assets' => [
        'EXA' => [
            'code' => 'EXA',
            'network' => env('EXA_NETWORK', 'base'),
            'decimals' => 18,
        ],
        'USDC' => [
            'code' => 'USDC',
            'network' => env('USDC_NETWORK', 'base'),
            'decimals' => 6,
        ],
        'USDT' => [
            'code' => 'USDT',
            'network' => env('USDT_NETWORK', 'base'),
            'decimals' => 6,
        ],
        'XRP' => [
            'code' => 'XRP',
            'network' => env('XRP_NETWORK', 'xrpl'),
            'decimals' => 6,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deposit confirmations
    |--------------------------------------------------------------------------
    */
    'confirmations' => [
        'base' => (int) env('BASE_MIN_CONFIRMATIONS', 12),
        'xrpl' => (int) env('XRPL_MIN_CONFIRMATIONS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Node.js blockchain service
    |--------------------------------------------------------------------------
    |
    | Laravel never stores private keys. Node service generates addresses,
    | monitors deposits, and broadcasts withdrawals.
    |
    */
    'node' => [
        'url' => env('NODE_SERVICE_URL', 'http://localhost:4000'),
        'secret' => env('NODE_SERVICE_SECRET', ''),
        'timeout_seconds' => (int) env('NODE_SERVICE_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Withdrawal protection
    |--------------------------------------------------------------------------
    */
    'withdrawals' => [
        'cooldown_seconds' => (int) env('WITHDRAWAL_COOLDOWN_SECONDS', 60),
        'per_minute_rate_limit' => (int) env('WITHDRAWAL_RATE_PER_MINUTE', 3),
    ],
];
