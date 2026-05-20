<?php

declare(strict_types=1);

return [
    'treasury_user_id' => env('TREASURY_USER_ID', 'system_treasury'),

    'assets' => ['USDT', 'BTC', 'ETH', 'NGN'],

    'withdrawal' => [
        'bps' => [
            'USDT' => env('FEE_WITHDRAWAL_USDT_BPS', 10),
            'BTC' => env('FEE_WITHDRAWAL_BTC_BPS', 5),
            'ETH' => env('FEE_WITHDRAWAL_ETH_BPS', 8),
            'NGN' => env('FEE_WITHDRAWAL_NGN_BPS', 50),
        ],
        'fixed' => [
            'USDT' => env('FEE_WITHDRAWAL_USDT_FIXED', '1'),
            'BTC' => env('FEE_WITHDRAWAL_BTC_FIXED', '0.00005'),
            'ETH' => env('FEE_WITHDRAWAL_ETH_FIXED', '0.001'),
            'NGN' => env('FEE_WITHDRAWAL_NGN_FIXED', '100'),
        ],
    ],

    'spot' => [
        'maker_bps' => env('FEE_SPOT_MAKER_BPS', 10),
        'taker_bps' => env('FEE_SPOT_TAKER_BPS', 20),
    ],

    'futures' => [
        'maker_bps' => env('FEE_FUTURES_MAKER_BPS', 2),
        'taker_bps' => env('FEE_FUTURES_TAKER_BPS', 5),
    ],

    'fiat_deposit' => [
        'bps' => [
            'NGN' => env('FEE_FIAT_DEPOSIT_NGN_BPS', 150),
        ],
        'fixed' => [
            'NGN' => env('FEE_FIAT_DEPOSIT_NGN_FIXED', '0'),
        ],
    ],
];
