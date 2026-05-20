<?php

declare(strict_types=1);

return [
    'auth' => [
        'max_login_attempts' => (int) env('SECURITY_MAX_LOGIN_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('SECURITY_LOGIN_DECAY_SECONDS', 60),
        'strong_password_regex' => (string) env('SECURITY_STRONG_PASSWORD_REGEX', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{10,}$/'),
    ],

    'api' => [
        'rate_limit_per_minute' => (int) env('SECURITY_API_RATE_LIMIT_PER_MINUTE', 120),
        'signature_required' => (bool) env('SECURITY_API_SIGNATURE_REQUIRED', false),
        'signature_ttl_seconds' => (int) env('SECURITY_API_SIGNATURE_TTL', 120),
        'signature_secret' => (string) env('SECURITY_API_SIGNATURE_SECRET', ''),
        'nonce_ttl_seconds' => (int) env('SECURITY_NONCE_TTL_SECONDS', 120),
        'sensitive_patterns' => [
            'api/wallet/withdraw',
            'api/withdrawals/initiate',
            'api/fiat-withdrawals/initiate',
            'api/admin/treasury/*',
        ],
    ],

    'admin' => [
        'require_2fa' => (bool) env('SECURITY_ADMIN_REQUIRE_2FA', true),
        'ip_whitelist' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_ADMIN_IP_WHITELIST', ''))))),
    ],

    'transactions' => [
        'withdrawal_daily_limit' => (string) env('SECURITY_WITHDRAWAL_DAILY_LIMIT', '10000'),
        'withdrawal_delay_seconds' => (int) env('SECURITY_WITHDRAWAL_DELAY_SECONDS', 60),
        'max_withdrawal_per_minute' => (int) env('SECURITY_MAX_WITHDRAWALS_PER_MINUTE', 3),
        'max_withdrawal_per_day' => (int) env('SECURITY_MAX_WITHDRAWALS_PER_DAY', 20),
        'large_withdrawal_threshold' => (string) env('SECURITY_LARGE_WITHDRAWAL_THRESHOLD', '2000'),
    ],
];

