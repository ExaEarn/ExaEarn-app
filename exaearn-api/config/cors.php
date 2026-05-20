<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter([
        env('FRONTEND_URL'),
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ]),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    // Set true if you use Sanctum SPA cookie authentication.
    // If you're using bearer tokens only, leaving this false is fine.
    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', false),
];
