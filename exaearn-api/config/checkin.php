<?php

return [
    'redemption_threshold_points' => 5000,
    'redemption_usdt_value' => 5,
    'daily_average_points' => 2.476,
    'mystery_streak_days' => 7,
    'trading_credit_expiry_days' => 30,

    'captcha_enabled' => env('CHECKIN_CAPTCHA_ENABLED', false),
    'captcha_secret' => env('CHECKIN_CAPTCHA_SECRET'),

    'limits' => [
        'claims_per_day' => 1,
        'accounts_per_ip' => 3,
        'accounts_per_device' => 3,
        'cooldown_seconds' => 30,
    ],

    'daily_rewards' => [
        ['points' => 0, 'weight' => 450],
        ['points' => 2, 'weight' => 250],
        ['points' => 3, 'weight' => 150],
        ['points' => 5, 'weight' => 80],
        ['points' => 8, 'weight' => 50],
        ['points' => 12, 'weight' => 18],
        ['points' => 20, 'weight' => 2],
    ],

    'mystery_rewards' => [
        ['points' => 10, 'weight' => 400],
        ['points' => 15, 'weight' => 300],
        ['points' => 20, 'weight' => 150],
        ['points' => 30, 'weight' => 100],
        ['points' => 50, 'weight' => 40],
        ['points' => 80, 'weight' => 10],
    ],
];
