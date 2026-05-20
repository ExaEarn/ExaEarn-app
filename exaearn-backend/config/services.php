<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'node' => [
        'service_url' => env('NODE_SERVICE_URL'),
        'webhook_secret' => env('NODE_WEBHOOK_SECRET'),
    ],

    'fx' => [
        'url' => env('FX_API_URL', 'https://open.er-api.com/v6/latest'),
    ],

    'binance' => [
        'url' => env('BINANCE_API_URL', 'https://api.binance.com'),
        'key' => env('BINANCE_API_KEY'),
        'secret' => env('BINANCE_API_SECRET'),
        'simulate' => (bool) env('BINANCE_SIMULATE', true),
    ],

    'flutterwave' => [
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
    ],

    'nomba' => [
        'webhook_secret' => env('NOMBA_WEBHOOK_SECRET'),
    ],

    'firebase' => [
        'api_key' => env('FIREBASE_API_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

];
