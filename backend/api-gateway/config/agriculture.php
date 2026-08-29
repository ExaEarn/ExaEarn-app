<?php

return [
    'mode' => env('AGRITECH_MODE', 'sandbox'),
    'public_investment_enabled' => (bool) env('AGRITECH_PUBLIC_INVESTMENT_ENABLED', false),
    'tokenized_investment_enabled' => (bool) env('AGRITECH_TOKENIZED_INVESTMENT_ENABLED', false),
    'external_verification_required' => true,
    'financial' => [
        'default_asset' => env('AGRITECH_DEFAULT_ASSET', 'USDT'),
        'high_value_disbursement_threshold' => env('AGRITECH_HIGH_VALUE_DISBURSEMENT_THRESHOLD', '10000'),
    ],
    'product_types' => ['FARM_PROJECT', 'FARM_SHARE', 'CROP_FUNDING', 'LEASE', 'PRODUCE_MARKETPLACE', 'REWARD_PROGRAM'],
    'economic_types' => ['NON_INVESTMENT_SUPPORT', 'PREPURCHASE', 'LEASE', 'REWARD_BASED', 'REVENUE_SHARE', 'INVESTMENT', 'TOKENIZED_INVESTMENT'],
    'blockchain' => [
        'enabled' => env('AGRI_BLOCKCHAIN_ENABLED', false),
    ],
    'rewards' => [
        'investment_activity' => env('AGRI_REWARD_ACTIVITY_INVESTMENT', 'agriculture_reward'),
        'farmer_support_activity' => env('AGRI_REWARD_ACTIVITY_FARMER_SUPPORT', 'agriculture_reward'),
        'funding_multiplier' => env('AGRI_REWARD_FUNDING_MULTIPLIER', '0.005'),
    ],
    'harvest' => [
        'default_investor_profit_share' => (int) env('AGRI_DEFAULT_INVESTOR_PROFIT_SHARE', 70),
        'default_farmer_profit_share' => (int) env('AGRI_DEFAULT_FARMER_PROFIT_SHARE', 30),
    ],
    'statuses' => [
        'projects' => ['draft', 'open', 'funded', 'active', 'harvested', 'closed', 'cancelled'],
        'farmers' => ['pending', 'approved', 'rejected', 'suspended'],
        'investments' => ['pending', 'confirmed', 'locked', 'settled', 'cancelled'],
        'leases' => ['pending', 'active', 'completed', 'terminated'],
        'produce_updates' => ['pending_review', 'verified', 'rejected'],
    ],
];
