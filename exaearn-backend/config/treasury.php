<?php

return [
    'withdrawal_rules' => [
        'auto_threshold' => 100,
        'risk_check_threshold' => 1000,
        'admin_threshold' => 5000,
        'cold_wallet_threshold' => 10000,
        'manual_threshold' => 50000,
    ],

    'asset_usd_rates' => [
        'USDT' => 1.0,
        'USDC' => 1.0,
        'BTC' => 50000.0,
        'ETH' => 3000.0,
        'USDT' => 1.0,
    ],

    'hot_wallet' => [
        'max_balance' => 100000,
        'min_balance' => 1000,
    ],

    'cold_wallet' => [
        'offline' => true,
        'manual_transfers' => true,
    ],

    'security' => [
        'encrypt_keys' => true,
        'key_secret' => env('TREASURY_KEY_SECRET'),
        'key_rotation_days' => 90,
        'multi_sig_required' => false,
    ],
];
