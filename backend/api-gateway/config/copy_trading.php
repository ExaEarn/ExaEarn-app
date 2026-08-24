<?php

declare(strict_types=1);

return [
    'default_profit_share_rate' => (string) env('COPY_TRADING_DEFAULT_PROFIT_SHARE_RATE', '0.10'),
    'min_profit_share_rate' => (string) env('COPY_TRADING_MIN_PROFIT_SHARE_RATE', '0'),
    'max_profit_share_rate' => (string) env('COPY_TRADING_MAX_PROFIT_SHARE_RATE', '0.30'),
    'default_max_leverage' => (int) env('COPY_TRADING_DEFAULT_MAX_LEVERAGE', 3),
    'max_event_age_seconds' => (int) env('COPY_TRADING_MAX_EVENT_AGE_SECONDS', 300),
    'max_copy_slippage_bps' => (string) env('COPY_TRADING_MAX_SLIPPAGE_BPS', '100'),
    'max_followers_per_lead' => (int) env('COPY_TRADING_MAX_FOLLOWERS_PER_LEAD', 10000),
    'max_aum_per_lead' => (string) env('COPY_TRADING_MAX_AUM_PER_LEAD', '10000000'),
    'payment_schedule' => (string) env('COPY_TRADING_PROFIT_SHARE_SCHEDULE', 'weekly'),
    'operations_status' => (string) env('COPY_TRADING_OPERATIONS_STATUS', 'not_staffed'),
    'compliance_approval' => (string) env('COPY_TRADING_COMPLIANCE_APPROVAL', 'required'),
    'production_launch_mode' => (string) env('COPY_TRADING_PRODUCTION_LAUNCH_MODE', 'limited_release'),
    'public' => [
        'mode' => (string) env('COPY_TRADING_MODE', 'DISABLED'),
        'require_2fa' => (bool) env('COPY_TRADING_PUBLIC_REQUIRE_2FA', true),
        'minimum_futures_kyc_level' => (int) env('COPY_TRADING_PUBLIC_MIN_FUTURES_KYC_LEVEL', 1),
        'lead_criteria_configured' => (bool) env('COPY_TRADING_LEAD_CRITERIA_CONFIGURED', true),
        'incident_response_configured' => (bool) env('COPY_TRADING_INCIDENT_RESPONSE_CONFIGURED', true),
        'compliance_status' => (string) env('COPY_TRADING_PUBLIC_COMPLIANCE_STATUS', 'PENDING'),
        'legal_approval_status' => (string) env('COPY_TRADING_PUBLIC_LEGAL_STATUS', 'PENDING'),
        'flags' => [
            'spot_copy_public' => (string) env('SPOT_COPY_PUBLIC', 'DISABLED'),
            'futures_copy_public' => (string) env('FUTURES_COPY_PUBLIC', 'DISABLED'),
            'lead_trader_applications_public' => (string) env('LEAD_TRADER_APPLICATIONS_PUBLIC', 'DISABLED'),
            'profit_share_public' => (string) env('PROFIT_SHARE_PUBLIC', 'DISABLED'),
        ],
        'operation_permissions' => [
            'copy.leads.review',
            'copy.leads.approve',
            'copy.leads.suspend',
            'copy.surveillance.view',
            'copy.surveillance.resolve',
            'copy.complaints.view',
            'copy.complaints.resolve',
            'copy.capacity.manage',
            'copy.emergency.manage',
            'copy.production.manage',
        ],
    ],
];
