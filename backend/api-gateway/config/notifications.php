<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */

    /**
     * Default notification channels
     */
    'default_channels' => ['in_app', 'email', 'push'],

    /**
     * Notification retention (days)
     */
    'retention_days' => 90,

    /**
     * Max retry attempts for failed notifications
     */
    'max_retries' => 3,

    /**
     * Email configuration
     */
    'email' => [
        'enabled' => env('MAIL_FROM_ADDRESS') !== null,
        'from' => env('MAIL_FROM_ADDRESS', 'noreply@exaearn.com'),
        'from_name' => env('MAIL_FROM_NAME', 'ExaEarn'),
    ],

    /**
     * Push notification (Firebase) configuration
     */
    'push' => [
        'enabled' => env('FIREBASE_API_KEY') !== null,
        'provider' => 'firebase', // or 'onesignal'
    ],

    /**
     * Notification types and their default channels
     */
    'types' => [
        'withdrawal_success' => ['in_app', 'email', 'push'],
        'withdrawal_failed' => ['in_app', 'email'],
        'deposit_confirmed' => ['in_app', 'email', 'push'],
        'deposit_pending' => ['in_app'],
        'system_alert' => ['in_app', 'email'],
        'trading_alert' => ['in_app', 'push'],
        'reward_earned' => ['in_app', 'push'],
        'kyc_required' => ['in_app', 'email'],
        'account_security' => ['in_app', 'email'],
        'maintenance_alert' => ['in_app', 'email'],
    ],

    /**
     * Notification queue configuration
     */
    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'database'),
        'name' => 'notifications',
        'timeout' => 60,
    ],

    /**
     * Cleanup configuration
     */
    'cleanup' => [
        'enabled' => true,
        'retention_days' => 90,
        'schedule' => '0 2 * * *', // 2 AM daily
    ],
];
