<?php

$configuredFrontendOrigins = array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('FRONTEND_URL', ''))
));

$frontendOrigins = array_merge([
    'https://exaearn.com',
    'https://app.exaearn.com',
    'https://admin.exaearn.com',
    'https://developers.exaearn.com',
    'https://listing.exaearn.com',
], $configuredFrontendOrigins);

$defaultDevOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5174',
    'http://localhost:5175',
    'http://127.0.0.1:5175',
    'http://localhost:5176',
    'http://127.0.0.1:5176',
    'http://localhost:5177',
    'http://127.0.0.1:5177',
    'http://localhost:8082',
    'http://127.0.0.1:8082',
];

$isProduction = env('APP_ENV') === 'production';
$allowedOrigins = $isProduction
    ? $frontendOrigins
    : array_merge($frontendOrigins, $defaultDevOrigins);
$allowedOriginPatterns = $isProduction
    ? []
    : ['#^https?://(localhost|127\\.0\\.0\\.1):517[0-9]$#'];

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique($allowedOrigins)),

    'allowed_origins_patterns' => $allowedOriginPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
