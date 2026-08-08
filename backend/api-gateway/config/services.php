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

    'fiat_gateway' => [
        'primary' => env('FIAT_WITHDRAWAL_PROVIDER', env('FIAT_GATEWAY_PRIMARY', 'sandbox')),
        'fallback' => env('FIAT_GATEWAY_FALLBACK', 'sandbox'),
        'sandbox_webhook_secret' => env('FIAT_SANDBOX_WEBHOOK_SECRET'),
    ],


    'market_data' => [
        'timeout_seconds' => (float) env('MARKET_DATA_TIMEOUT_SECONDS', 0.75),
        'retries' => (int) env('MARKET_DATA_RETRIES', 0),
        'snapshot_cache_seconds' => (int) env('MARKET_DATA_SNAPSHOT_CACHE_SECONDS', 60),
        'skip_external_on_local_request' => (bool) env('MARKET_DATA_SKIP_EXTERNAL_ON_LOCAL_REQUEST', true),
    ],
    'binance' => [
        'url' => env('BINANCE_API_URL', 'https://api.binance.com'),
        'key' => env('BINANCE_API_KEY'),
        'secret' => env('BINANCE_API_SECRET'),
        'simulate' => (bool) env('BINANCE_SIMULATE', true),
    ],

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'payment_url' => env('FLUTTERWAVE_PAYMENT_URL', 'https://api.flutterwave.com/v3/payments'),
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH', env('FLUTTERWAVE_WEBHOOK_SECRET')),
    ],

    'nomba' => [
        'api_key' => env('NOMBA_API_KEY'),
        'token' => env('NOMBA_TOKEN'),
        'url' => env('NOMBA_URL', 'https://api.nomba.com'),
        'account_id' => env('NOMBA_ACCOUNT_ID'),
        'checkout_url' => env('NOMBA_CHECKOUT_URL'),
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

    'staking_rpc' => [
        'sol' => ['primary' => env('STAKING_SOL_PRIMARY_RPC'), 'secondary' => env('STAKING_SOL_SECONDARY_RPC')],
        'eth' => ['primary' => env('STAKING_ETH_PRIMARY_RPC'), 'secondary' => env('STAKING_ETH_CONSENSUS_RPC')],
        'ada' => ['primary' => env('STAKING_ADA_PRIMARY_RPC'), 'secondary' => env('STAKING_ADA_SECONDARY_RPC')],
        'bnb' => ['primary' => env('STAKING_BNB_PRIMARY_RPC'), 'secondary' => env('STAKING_BNB_SECONDARY_RPC')],
        'avax' => ['primary' => env('STAKING_AVAX_PRIMARY_RPC'), 'secondary' => env('STAKING_AVAX_SECONDARY_RPC')],
        'sui' => ['primary' => env('STAKING_SUI_PRIMARY_RPC'), 'secondary' => env('STAKING_SUI_SECONDARY_RPC')],
        'dot' => ['primary' => env('STAKING_DOT_PRIMARY_RPC'), 'secondary' => env('STAKING_DOT_SECONDARY_RPC')],
        'atom' => ['primary' => env('STAKING_ATOM_PRIMARY_RPC'), 'secondary' => env('STAKING_ATOM_SECONDARY_RPC')],
        'near' => ['primary' => env('STAKING_NEAR_PRIMARY_RPC'), 'secondary' => env('STAKING_NEAR_SECONDARY_RPC')],
        'xtz' => ['primary' => env('STAKING_XTZ_PRIMARY_RPC'), 'secondary' => env('STAKING_XTZ_SECONDARY_RPC')],
        'pol' => ['primary' => env('STAKING_POL_PRIMARY_RPC'), 'secondary' => env('STAKING_POL_SECONDARY_RPC')],
    ],

    'staking_secure_signer' => [
        'url' => env('STAKING_SECURE_SIGNER_URL'),
        'key_reference' => env('STAKING_SECURE_SIGNER_KEY_REFERENCE'),
        'secret' => env('STAKING_SECURE_SIGNER_SECRET'),
        'timeout_seconds' => env('STAKING_SECURE_SIGNER_TIMEOUT_SECONDS', 15),
    ],

];
