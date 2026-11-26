<?php

declare(strict_types=1);

return [
    // Disabled by default. Production is always denied by the service even if this flag is mis-set.
    'enabled' => (bool) env('EXAPAY_OFFLINE_SIMULATION_ENABLED', false),
    'redemption_enabled' => (bool) env('EXAPAY_OFFLINE_REDEMPTION_ENABLED', false),
    'asset' => 'SIMUSDT',
    'minor_units_per_token' => 100,
    'allowed_environments' => ['testing', 'staging'],
    'protocol_version' => 1,
    'signature_algorithm' => 'RSA-SHA256',
    'issuer_private_key' => env('EXAPAY_OFFLINE_ISSUER_PRIVATE_KEY'),
    'issuer_private_key_passphrase' => env('EXAPAY_OFFLINE_ISSUER_PRIVATE_KEY_PASSPHRASE'),
    'proof_max_age_seconds' => 300,
    'defaults' => [
        'enabled' => false,
        'max_amount_minor' => 10000,
        'max_outstanding_per_user_minor' => 50000,
        'max_devices_per_user' => 3,
        'instrument_lifetime_seconds' => 86400,
    ],
];
