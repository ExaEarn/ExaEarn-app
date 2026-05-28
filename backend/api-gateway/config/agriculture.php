<?php

return [
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
