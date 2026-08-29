<?php

declare(strict_types=1);

return [
    'policy_version' => env('COMPLIANCE_POLICY_VERSION', 'phase16-v1'),
    'cache_ttl_seconds' => (int) env('COMPLIANCE_CACHE_TTL_SECONDS', 30),
    'default_verified_country' => env('COMPLIANCE_DEFAULT_COUNTRY'),
    'high_risk_products' => [
        'FUTURES', 'MARGIN', 'FIAT_WITHDRAWAL', 'CRYPTO_WITHDRAWAL', 'COPY_TRADING_FUTURES',
        'EXAAI_FUTURES', 'API_TRADING', 'OTC', 'MARKET_MAKING', 'MM_BOT', 'AGRITECH_INVESTMENT',
    ],
    'products' => [
        'SPOT' => ['risk_category' => 'STANDARD', 'default_policy' => 'REQUIRE_KYC'],
        'FUTURES' => ['risk_category' => 'HIGH', 'default_policy' => 'DENY'],
        'MARGIN' => ['risk_category' => 'HIGH', 'default_policy' => 'DENY'],
        'CONVERT' => ['risk_category' => 'STANDARD', 'default_policy' => 'REQUIRE_KYC'],
        'P2P' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'EARN' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'CRYPTO_DEPOSIT' => ['risk_category' => 'STANDARD', 'default_policy' => 'REQUIRE_KYC'],
        'CRYPTO_WITHDRAWAL' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'FIAT_DEPOSIT' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'FIAT_WITHDRAWAL' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'COPY_TRADING_SPOT' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'COPY_TRADING_FUTURES' => ['risk_category' => 'HIGH', 'default_policy' => 'DENY'],
        'EXAAI_SPOT' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'EXAAI_FUTURES' => ['risk_category' => 'HIGH', 'default_policy' => 'DENY'],
        'GIFTCARD' => ['risk_category' => 'STANDARD', 'default_policy' => 'REQUIRE_KYC'],
        'NFT' => ['risk_category' => 'STANDARD', 'default_policy' => 'REQUIRE_KYC'],
        'CROWDFUND' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'AGRITECH' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'AGRITECH_INVESTMENT' => ['risk_category' => 'HIGH', 'default_policy' => 'DENY'],
        'EXASKILLS' => ['risk_category' => 'LOW', 'default_policy' => 'ALLOW'],
        'GAME' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'CARD' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYC'],
        'API_TRADING' => ['risk_category' => 'HIGH', 'default_policy' => 'DENY'],
        'INSTITUTIONAL' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYB'],
        'MARKET_MAKING' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYB'],
        'OTC' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYB'],
        'MM_BOT' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_KYB'],
        'TOKEN_LISTING' => ['risk_category' => 'HIGH', 'default_policy' => 'REQUIRE_ENHANCED_REVIEW'],
    ],
];
