<?php

return [
    'account_terms_version' => env('EXAEARN_ACCOUNT_TERMS_VERSION', 'terms-2026-01'),
    'portal_terms_version' => env('LISTING_PORTAL_TERMS_VERSION', 'listing-2026-01'),
    'kyb_declaration_version' => env('LISTING_KYB_DECLARATION_VERSION', 'listing-kyb-2026-01'),
    'ubo_thresholds' => ['DEFAULT' => 25],
    'required_kyb_documents' => ['INCORPORATION_CERTIFICATE', 'SHAREHOLDER_REGISTER', 'REGISTERED_ADDRESS_PROOF', 'AUTHORIZATION_LETTER'],
    'require_approved_kyb_for_listing_submission' => (bool) env('LISTING_REQUIRE_APPROVED_KYB', true),
    'screening_provider_connected' => (bool) env('LISTING_SCREENING_PROVIDER_CONNECTED', false),
    'registry_provider_connected' => (bool) env('LISTING_REGISTRY_PROVIDER_CONNECTED', false),
    'malware_scanner_connected' => (bool) env('LISTING_MALWARE_SCANNER_CONNECTED', false),
    'malware_scanner_adapter' => env('LISTING_MALWARE_SCANNER_ADAPTER', 'not_connected'),
    'required_listing_documents' => array_values(array_filter(array_map('trim', explode(',', env('LISTING_REQUIRED_DOCUMENTS', ''))))),
    'documents' => [
        'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg'],
        'allowed_mime_types' => ['application/pdf', 'image/png', 'image/jpeg'],
        'max_size_kb' => (int) env('LISTING_DOCUMENT_MAX_SIZE_KB', 20480),
        'scan_timeout_seconds' => (int) env('LISTING_MALWARE_SCAN_TIMEOUT', 30),
        'scan_retries' => (int) env('LISTING_MALWARE_SCAN_RETRIES', 3),
        'infected_retention_days' => (int) env('LISTING_INFECTED_RETENTION_DAYS', 30),
        'manual_safe_review_available' => false,
    ],
    'integration_certification_execution_enabled' => (bool) env('LISTING_CERTIFICATION_EXECUTION_ENABLED', false),
    'launch' => [
        'maximum_lateness_minutes' => (int) env('LISTING_LAUNCH_MAXIMUM_LATENESS_MINUTES', 60),
        'dispatch_lease_seconds' => (int) env('LISTING_LAUNCH_DISPATCH_LEASE_SECONDS', 120),
        'execution_lease_seconds' => (int) env('LISTING_LAUNCH_EXECUTION_LEASE_SECONDS', 180),
        'max_attempts' => (int) env('LISTING_LAUNCH_MAX_ATTEMPTS', 3),
    ],
];
