<?php

return [
    'currency' => env('GIFTCARD_SETTLEMENT_CURRENCY', 'USDT'),
    
    'fraud' => [
        'auto_approve_max' => env('GIFTCARD_AUTO_APPROVE_MAX', '50'),
        'admin_review_min' => env('GIFTCARD_ADMIN_REVIEW_MIN', '100'),
        'low_risk_max_score' => (int) env('GIFTCARD_LOW_RISK_MAX_SCORE', 39),
        'medium_risk_max_score' => (int) env('GIFTCARD_MEDIUM_RISK_MAX_SCORE', 69),
        'freeze_high_risk_users' => env('GIFTCARD_FREEZE_HIGH_RISK_USERS', false),
    ],
    
    'limits' => [
        'sell_per_minute' => (int) env('GIFTCARD_MAX_SELL_PER_MINUTE', 3),
        'buy_per_minute' => (int) env('GIFTCARD_MAX_BUY_PER_MINUTE', 5),
    ],
    
    'providers' => [
        'amazon' => [
            'verified_source' => true,
            'api_fee_percent' => 0.02,  // 2% API fee
            'delivery_fee_fixed' => 0.00,
            'user_fee_strategy' => 'pass_to_user', // pass_to_user | absorb | split
        ],
        'apple' => [
            'verified_source' => true,
            'api_fee_percent' => 0.03,  // 3% API fee
            'delivery_fee_fixed' => 0.00,
            'user_fee_strategy' => 'pass_to_user',
        ],
        'steam' => [
            'verified_source' => true,
            'api_fee_percent' => 0.025,  // 2.5% API fee
            'delivery_fee_fixed' => 0.00,
            'user_fee_strategy' => 'split',
            'split_ratio' => 0.5,  // Platform absorbs 50%, user pays 50%
        ],
        'google_play' => [
            'verified_source' => true,
            'api_fee_percent' => 0.02,
            'delivery_fee_fixed' => 0.00,
            'user_fee_strategy' => 'pass_to_user',
        ],
        'manual_upload' => [
            'verified_source' => false,
            'api_fee_percent' => 0.00,
            'delivery_fee_fixed' => 0.00,
            'user_fee_strategy' => 'pass_to_user',
        ],
    ],
    
    'fee_management' => [
        'platform_margin_percent' => (float) env('GIFTCARD_PLATFORM_MARGIN', 0.01), // 1% platform margin on absorbed fees
        'treasury_user_id' => (int) env('GIFTCARD_TREASURY_USER_ID', 1), // Admin/system user ID for treasury
        'fee_rounding_mode' => 'up', // round_up | round_down | round_half
        'min_platform_profit' => (float) env('GIFTCARD_MIN_PLATFORM_PROFIT', 0.01), // Minimum $0.01 profit per transaction
    ],
    
    'wallet_types' => ['funding', 'spot', 'savings'],
    
    'settlement' => [
        'auto_settle_pending_minutes' => (int) env('GIFTCARD_AUTO_SETTLE_MINUTES', 60),
        'max_pending_orders' => (int) env('GIFTCARD_MAX_PENDING_ORDERS', 1000),
    ],
];
