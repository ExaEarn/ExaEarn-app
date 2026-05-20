<?php

declare(strict_types=1);

return [
    /**
     * Global rate limiting configuration
     */
    'enabled' => env('SECURITY_RATELIMIT_ENABLED', true),

    /**
     * Redis connection for rate limiting
     */
    'cache' => env('CACHE_DRIVER', 'redis'),

    /**
     * Endpoint-based rate limits (requests per minute)
     */
    'limits' => [
        // Authentication endpoints
        'auth.login' => [
            'default' => 5,        // 5 requests per minute
            'suspicious' => 2,     // 2 requests if flagged as suspicious
            'blocked' => 0,        // completely blocked
        ],
        'auth.register' => [
            'default' => 3,
            'suspicious' => 1,
            'blocked' => 0,
        ],
        'auth.password_reset' => [
            'default' => 3,
            'suspicious' => 1,
            'blocked' => 0,
        ],

        // Gift card endpoints
        'giftcard.purchase' => [
            'default' => 10,
            'suspicious' => 3,
            'blocked' => 0,
        ],
        'giftcard.sell' => [
            'default' => 10,
            'suspicious' => 3,
            'blocked' => 0,
        ],

        // Financial endpoints
        'wallet.withdraw' => [
            'default' => 5,
            'suspicious' => 1,
            'blocked' => 0,
        ],
        'buy.token' => [
            'default' => 20,
            'suspicious' => 5,
            'blocked' => 0,
        ],
        'trade.execute' => [
            'default' => 30,
            'suspicious' => 10,
            'blocked' => 0,
        ],

        // Default fallback
        'default' => [
            'default' => 60,
            'suspicious' => 20,
            'blocked' => 0,
        ],
    ],

    /**
     * Time window in seconds (rate limit window)
     */
    'window' => 60,

    /**
     * Bot detection configuration
     */
    'bot_detection' => [
        'enabled' => true,

        // Request frequency threshold (requests per 10 seconds)
        'frequency_threshold' => 10,

        // Consecutive failed attempts before flagging
        'failed_attempts_threshold' => 5,

        // Time window for failed attempts (seconds)
        'failed_attempts_window' => 300,

        // Block duration (seconds)
        'block_duration' => 900, // 15 minutes

        // Patterns to detect
        'patterns' => [
            'rapid_requests' => true,
            'repeated_failures' => true,
            'suspicious_headers' => true,
            'unusual_user_agents' => true,
            'missing_frontend_signals' => true,
        ],
    ],

    /**
     * IP blocking configuration
     */
    'ip_blocking' => [
        'enabled' => true,
        'failed_login_threshold' => 10,
        'block_duration' => 900, // 15 minutes
    ],

    /**
     * Device tracking configuration
     */
    'device_tracking' => [
        'enabled' => true,
        'max_accounts_per_device' => 3,
        'anomaly_detection' => true,
    ],

    /**
     * CAPTCHA configuration
     */
    'captcha' => [
        'enabled' => true,
        'provider' => env('CAPTCHA_PROVIDER', 'google'), // 'google' or 'hcaptcha'
        'google' => [
            'site_key' => env('GOOGLE_RECAPTCHA_SITE_KEY', ''),
            'secret_key' => env('GOOGLE_RECAPTCHA_SECRET_KEY', ''),
            'threshold' => 0.5,
        ],
        'hcaptcha' => [
            'site_key' => env('HCAPTCHA_SITE_KEY', ''),
            'secret_key' => env('HCAPTCHA_SECRET_KEY', ''),
        ],
        'trigger_on' => [
            'suspicious_login' => true,
            'bot_detection' => true,
            'rate_limit_approached' => true,
        ],
    ],

    /**
     * Suspicious user agents to block
     */
    'blocked_user_agents' => [
        'bot',
        'crawler',
        'spider',
        'scraper',
        'curl',
        'wget',
        'python',
        'java',
        'node',
    ],

    /**
     * Logging configuration
     */
    'logging' => [
        'enabled' => true,
        'log_failed_attempts' => true,
        'log_rate_limit_hits' => true,
        'log_bot_detections' => true,
        'log_ip_blocks' => true,
    ],

    /**
     * Admin settings
     */
    'admin' => [
        'view_analytics' => true,
        'manage_blocked_ips' => true,
        'adjust_limits' => true,
        'whitelist_ips' => true,
    ],

    /**
     * Whitelist IPs (never rate limited)
     */
    'whitelist' => [
        'ips' => explode(',', env('SECURITY_WHITELIST_IPS', '127.0.0.1,::1')),
    ],

    /**
     * Blacklist IPs (always blocked)
     */
    'blacklist' => [
        'ips' => explode(',', env('SECURITY_BLACKLIST_IPS', '')),
    ],
];
