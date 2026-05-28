<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Crypto Treasury Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for multi-chain crypto treasury management
    |
    */

    'chains' => [
        'base' => [
            'rpc_url' => env('BASE_RPC_URL', 'https://mainnet.base.org'),
            'chain_id' => 8453,
            'assets' => ['ETH', 'USDT'],
            'hot_wallet_address' => env('BASE_HOT_WALLET_ADDRESS'),
            'cold_wallet_address' => env('BASE_COLD_WALLET_ADDRESS'),
            'encrypted_private_key' => env('BASE_ENCRYPTED_PRIVATE_KEY'),
        ],
        'bitcoin' => [
            'rpc_url' => env('BITCOIN_RPC_URL'),
            'assets' => ['BTC'],
            'hot_wallet_address' => env('BITCOIN_HOT_WALLET_ADDRESS'),
            'cold_wallet_address' => env('BITCOIN_COLD_WALLET_ADDRESS'),
            'encrypted_private_key' => env('BITCOIN_ENCRYPTED_PRIVATE_KEY'),
        ],
        'xrp' => [
            'rpc_url' => env('XRP_RPC_URL'),
            'assets' => ['XRP'],
            'hot_wallet_address' => env('XRP_HOT_WALLET_ADDRESS'),
            'cold_wallet_address' => env('XRP_COLD_WALLET_ADDRESS'),
            'encrypted_private_key' => env('XRP_ENCRYPTED_PRIVATE_KEY'),
        ],
        'solana' => [
            'rpc_url' => env('SOLANA_RPC_URL', 'https://api.mainnet-beta.solana.com'),
            'assets' => ['SOL'],
            'hot_wallet_address' => env('SOLANA_HOT_WALLET_ADDRESS'),
            'cold_wallet_address' => env('SOLANA_COLD_WALLET_ADDRESS'),
            'encrypted_private_key' => env('SOLANA_ENCRYPTED_PRIVATE_KEY'),
        ],
        'tron' => [
            'rpc_url' => env('TRON_RPC_URL', 'https://api.trongrid.io'),
            'assets' => ['TRX'],
            'hot_wallet_address' => env('TRON_HOT_WALLET_ADDRESS'),
            'cold_wallet_address' => env('TRON_COLD_WALLET_ADDRESS'),
            'encrypted_private_key' => env('TRON_ENCRYPTED_PRIVATE_KEY'),
        ],
        'ton' => [
            'rpc_url' => env('TON_RPC_URL'),
            'assets' => ['TON'],
            'hot_wallet_address' => env('TON_HOT_WALLET_ADDRESS'),
            'cold_wallet_address' => env('TON_COLD_WALLET_ADDRESS'),
            'encrypted_private_key' => env('TON_ENCRYPTED_PRIVATE_KEY'),
        ],
    ],

    'large_withdrawal_threshold_usd' => env('LARGE_WITHDRAWAL_THRESHOLD_USD', 10000),

    'multi_signature' => [
        'required_signers' => env('MULTI_SIG_REQUIRED_SIGNERS', 2),
        'total_signers' => env('MULTI_SIG_TOTAL_SIGNERS', 3),
    ],
];