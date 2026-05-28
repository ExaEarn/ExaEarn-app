<?php

return [
    'enabled' => env('REFERRAL_ENABLED', true),
    'reward_token' => env('REFERRAL_REWARD_TOKEN', 'EXA'),
    'code_length' => (int) env('REFERRAL_CODE_LENGTH', 8),
    'max_depth' => (int) env('REFERRAL_MAX_DEPTH', 3),
    'frontend_register_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/register',
    'require_email_verification' => env('REFERRAL_REQUIRE_EMAIL_VERIFICATION', true),
    'require_kyc_for_rewards' => env('REFERRAL_REQUIRE_KYC', false),
    'suspend_hours' => (int) env('REFERRAL_SUSPEND_HOURS', 72),
    'levels' => [
        1 => (float) env('REFERRAL_LEVEL_1_SHARE', 0.70),
        2 => (float) env('REFERRAL_LEVEL_2_SHARE', 0.20),
        3 => (float) env('REFERRAL_LEVEL_3_SHARE', 0.10),
    ],
    'activities' => [
        'first_deposit' => [
            'reward' => env('REFERRAL_REWARD_FIRST_DEPOSIT', '100'),
            'first_only' => true,
        ],
        'first_trade' => [
            'reward' => env('REFERRAL_REWARD_FIRST_TRADE', '50'),
            'first_only' => true,
        ],
        'staking_participation' => [
            'reward' => env('REFERRAL_REWARD_STAKING_PARTICIPATION', '75'),
            'first_only' => true,
        ],
        'course_completion' => [
            'reward' => env('REFERRAL_REWARD_COURSE_COMPLETION', '25'),
            'first_only' => true,
        ],
    ],
    'leaderboard' => [
        'default_limit' => (int) env('REFERRAL_LEADERBOARD_LIMIT', 25),
    ],
    'abuse' => [
        'shared_ip_limit' => (int) env('REFERRAL_SHARED_IP_LIMIT', 3),
        'shared_fingerprint_limit' => (int) env('REFERRAL_SHARED_FINGERPRINT_LIMIT', 2),
        'shared_wallet_limit' => (int) env('REFERRAL_SHARED_WALLET_LIMIT', 1),
    ],
];
