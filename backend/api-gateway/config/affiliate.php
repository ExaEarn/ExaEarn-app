<?php

return [
    'enabled' => env('AFFILIATE_PROGRAM_ENABLED', true),
    'default_payout_asset' => env('AFFILIATE_DEFAULT_PAYOUT_ASSET', 'EXAPOINT'),
    'default_commission_rate_bps' => env('AFFILIATE_DEFAULT_COMMISSION_BPS', '1000'),
    'default_hold_days' => (int) env('AFFILIATE_DEFAULT_HOLD_DAYS', 14),
    'payout_methods' => ['EXAPOINT'],
    'minimum_payout' => [
        'EXAPOINT' => env('AFFILIATE_MINIMUM_EXAPOINT_PAYOUT', '1'),
    ],
    'commissionable_events' => [
        'EXAAI' => [
            'SUBSCRIPTION_PURCHASE' => [
                'enabled' => true,
                'hold_days' => (int) env('AFFILIATE_EXAAI_HOLD_DAYS', 7),
                'basis' => 'settled_fee_revenue',
            ],
        ],
        'SPOT' => [
            'FEE' => ['enabled' => false, 'hold_days' => 3, 'basis' => 'settled_fee_revenue'],
        ],
        'FUTURES' => [
            'FEE' => ['enabled' => false, 'hold_days' => 3, 'basis' => 'settled_fee_revenue'],
        ],
        'CONVERT' => [
            'FEE' => ['enabled' => false, 'hold_days' => 3, 'basis' => 'settled_fee_revenue'],
        ],
        'EXAPAY' => [
            'MERCHANT_FEE' => ['enabled' => false, 'hold_days' => 14, 'basis' => 'settled_fee_revenue'],
        ],
        'GIFTCARD' => [
            'PURCHASE_MARGIN' => ['enabled' => false, 'hold_days' => 14, 'basis' => 'settled_margin'],
        ],
    ],
];
