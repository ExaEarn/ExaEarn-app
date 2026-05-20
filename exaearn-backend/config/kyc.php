<?php

declare(strict_types=1);

return [
    'provider' => env('KYC_PROVIDER', 'smile'),
    'fallback' => env('KYC_FALLBACK_PROVIDER', 'sumsub'),

    'providers' => [
        'smile' => [
            'base_url' => env('SMILE_BASE_URL', 'https://api.smileidentity.com'),
            'api_key' => env('SMILE_API_KEY'),
        ],
        'sumsub' => [
            'base_url' => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),
            'api_key' => env('SUMSUB_API_KEY'),
        ],
    ],

    'limits' => [
        0 => '100',
        1 => '1000',
        2 => '10000',
        3 => '999999999',
    ],

    'rules' => [
        'safe_max' => 29,
        'flagged_max' => 70,
        'max_attempts_per_day' => 5,
        'upload_max_kb' => 5120,
        'allowed_doc_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
        'allowed_selfie_mimes' => ['image/jpeg', 'image/png'],
    ],

    'blacklist_countries' => explode(',', (string) env('KYC_BLACKLIST_COUNTRIES', 'KP,IR,SY')),
];
