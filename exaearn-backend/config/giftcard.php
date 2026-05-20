<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gift Card Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the gift card sell system including fraud
    | detection thresholds, supported brands, and processing settings.
    |
    */

    'fraud_detection' => [
        /*
        |--------------------------------------------------------------------------
        | Risk Thresholds
        |--------------------------------------------------------------------------
        |
        | Thresholds for automatic fraud detection decisions.
        | - auto_reject_threshold: Risk scores above this will be auto-rejected
        | - auto_approve_threshold: Risk scores below this will be auto-approved
        | - review_threshold: Risk scores between approve and reject go to review
        |
        */
        'auto_reject_threshold' => env('GIFTCARD_AUTO_REJECT_THRESHOLD', 0.8),
        'auto_approve_threshold' => env('GIFTCARD_AUTO_APPROVE_THRESHOLD', 0.1),
        'review_threshold' => env('GIFTCARD_REVIEW_THRESHOLD', 0.1),

        /*
        |--------------------------------------------------------------------------
        | Critical Flags
        |--------------------------------------------------------------------------
        |
        | Flags that will force a submission to review regardless of score
        |
        */
        'critical_flags' => [
            'blacklisted_user',
            'suspicious_ip',
            'duplicate_card_high_frequency',
            'unusual_card_value',
        ],

        /*
        |--------------------------------------------------------------------------
        | Risk Scoring Weights
        |--------------------------------------------------------------------------
        |
        | Weights for different risk factors in fraud scoring
        |
        */
        'weights' => [
            'user_history' => 0.3,
            'card_value' => 0.2,
            'behavior_patterns' => 0.3,
            'external_checks' => 0.2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Brands
    |--------------------------------------------------------------------------
    |
    | List of supported gift card brands and their configurations
    |
    */
    'supported_brands' => [
        'amazon' => [
            'name' => 'Amazon',
            'min_value' => 10,
            'max_value' => 500,
            'currencies' => ['USD', 'EUR', 'GBP'],
            'validation_api' => env('AMAZON_VALIDATION_API'),
        ],
        'google_play' => [
            'name' => 'Google Play',
            'min_value' => 5,
            'max_value' => 200,
            'currencies' => ['USD', 'EUR'],
            'validation_api' => env('GOOGLE_PLAY_VALIDATION_API'),
        ],
        'itunes' => [
            'name' => 'iTunes',
            'min_value' => 10,
            'max_value' => 500,
            'currencies' => ['USD', 'EUR', 'GBP'],
            'validation_api' => env('ITUNES_VALIDATION_API'),
        ],
        'steam' => [
            'name' => 'Steam',
            'min_value' => 5,
            'max_value' => 100,
            'currencies' => ['USD', 'EUR'],
            'validation_api' => env('STEAM_VALIDATION_API'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Settings
    |--------------------------------------------------------------------------
    |
    | Settings for gift card processing and validation
    |
    */
    'processing' => [
        'max_daily_submissions_per_user' => env('GIFTCARD_MAX_DAILY_SUBMISSIONS', 10),
        'max_simultaneous_pending' => env('GIFTCARD_MAX_PENDING', 5),
        'validation_timeout_seconds' => env('GIFTCARD_VALIDATION_TIMEOUT', 30),
        'auto_process_queue' => env('GIFTCARD_AUTO_PROCESS_QUEUE', 'giftcard'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Treasury Integration
    |--------------------------------------------------------------------------
    |
    | Settings for treasury payout integration
    |
    */
    'treasury' => [
        'payout_wallet_type' => env('GIFTCARD_PAYOUT_WALLET_TYPE', 'main'),
        'ledger_category' => 'giftcard_payout',
        'ledger_description_template' => 'Gift card payout for {brand} ${value} card',
    ],
];
