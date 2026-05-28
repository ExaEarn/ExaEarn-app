<?php

declare(strict_types=1);

return [
    'assets' => [
        'NGN' => [
            'code' => 'NGN',
            'network' => 'fiat',
            'decimals' => 2,
            'type' => 'fiat',
        ],
        'USD' => [
            'code' => 'USD',
            'network' => 'fiat',
            'decimals' => 2,
            'type' => 'fiat',
        ],
        'ETH' => [
            'code' => 'ETH',
            'network' => env('ETH_NETWORK', 'ethereum'),
            'decimals' => 18,
            'type' => 'native',
        ],
        'EXA' => [
            'code' => 'EXA',
            'network' => env('EXA_NETWORK', 'base'),
            'decimals' => 18,
            'type' => 'token',
        ],
        'USDT' => [
            'code' => 'USDT',
            'network' => env('USDT_NETWORK', 'base'),
            'decimals' => 6,
            'type' => 'token',
        ],
        'USDC' => [
            'code' => 'USDC',
            'network' => env('USDC_NETWORK', 'base'),
            'decimals' => 6,
            'type' => 'token',
        ],
        'BNB' => [
            'code' => 'BNB',
            'network' => env('BNB_NETWORK', 'bsc'),
            'decimals' => 18,
            'type' => 'native',
        ],
        'MATIC' => [
            'code' => 'MATIC',
            'network' => env('MATIC_NETWORK', 'polygon'),
            'decimals' => 18,
            'type' => 'native',
        ],
        'BTC' => [
            'code' => 'BTC',
            'network' => env('BTC_NETWORK', 'bitcoin'),
            'decimals' => 8,
            'type' => 'native',
        ],
        'XRP' => [
            'code' => 'XRP',
            'network' => env('XRP_NETWORK', 'xrpl'),
            'decimals' => 6,
            'type' => 'native',
        ],
        'TRX' => [
            'code' => 'TRX',
            'network' => env('TRX_NETWORK', 'tron'),
            'decimals' => 6,
            'type' => 'native',
        ],
        'SOL' => [
            'code' => 'SOL',
            'network' => env('SOL_NETWORK', 'solana'),
            'decimals' => 9,
            'type' => 'native',
        ],
        'TON' => [
            'code' => 'TON',
            'network' => env('TON_NETWORK', 'ton'),
            'decimals' => 9,
            'type' => 'native',
        ],
    ],

    'confirmations' => [
        'ethereum' => (int) env('ETHEREUM_MIN_CONFIRMATIONS', 12),
        'base' => (int) env('BASE_MIN_CONFIRMATIONS', 12),
        'bsc' => (int) env('BSC_MIN_CONFIRMATIONS', 15),
        'polygon' => (int) env('POLYGON_MIN_CONFIRMATIONS', 20),
        'bitcoin' => (int) env('BITCOIN_MIN_CONFIRMATIONS', 3),
        'xrpl' => (int) env('XRPL_MIN_CONFIRMATIONS', 1),
        'tron' => (int) env('TRON_MIN_CONFIRMATIONS', 20),
        'solana' => (int) env('SOLANA_MIN_CONFIRMATIONS', 20),
        'ton' => (int) env('TON_MIN_CONFIRMATIONS', 20),
    ],

    'chains' => [
        'ethereum' => [
            'family' => 'evm',
            'coin_type' => 60,
        ],
        'base' => [
            'family' => 'evm',
            'coin_type' => 60,
        ],
        'bsc' => [
            'family' => 'evm',
            'coin_type' => 60,
        ],
        'polygon' => [
            'family' => 'evm',
            'coin_type' => 60,
        ],
        'bitcoin' => [
            'family' => 'utxo',
            'coin_type' => 0,
        ],
        'xrpl' => [
            'family' => 'tagged',
            'coin_type' => 144,
        ],
        'tron' => [
            'family' => 'account',
            'coin_type' => 195,
        ],
        'solana' => [
            'family' => 'account',
            'coin_type' => 501,
        ],
        'ton' => [
            'family' => 'account',
            'coin_type' => 607,
        ],
    ],

    'node' => [
        'url' => env('NODE_SERVICE_URL', 'http://localhost:4000'),
        'secret' => env('NODE_SERVICE_SECRET', ''),
        'timeout_seconds' => (int) env('NODE_SERVICE_TIMEOUT', 15),
    ],

    'withdrawals' => [
        'cooldown_seconds' => (int) env('WITHDRAWAL_COOLDOWN_SECONDS', 60),
        'per_minute_rate_limit' => (int) env('WITHDRAWAL_RATE_PER_MINUTE', 3),
        'max_per_request' => env('WITHDRAWAL_MAX_PER_REQUEST', '10000'),
        'daily_limit' => env('WITHDRAWAL_DAILY_LIMIT', '25000'),
        'hot_wallet_max_auto' => env('HOT_WALLET_MAX_AUTO', '1000'),
        'anomaly_threshold' => env('WITHDRAWAL_ANOMALY_THRESHOLD', '5000'),
    ],

    'security' => [
        'api_rate_limit' => (int) env('API_RATE_LIMIT', 120),
        'webhook_rate_limit' => (int) env('WEBHOOK_RATE_LIMIT', 240),
    ],
];
