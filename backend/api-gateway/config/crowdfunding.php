<?php

return [
    'public_enabled' => env('CROWDFUNDING_PUBLIC_ENABLED', true),
    'sandbox_enabled' => env('CROWDFUNDING_SANDBOX_ENABLED', true),
    'investment_campaigns_enabled' => env('CROWDFUNDING_INVESTMENT_ENABLED', false),
    'default_asset' => env('CROWDFUNDING_DEFAULT_ASSET', 'USDT'),
    'storage' => [
        'disk' => env('CROWDFUNDING_STORAGE_DISK', 'local'),
    ],
    'operations_defaults' => [
        'new_campaign_creation_enabled' => ['enabled' => true],
        'new_campaign_submission_enabled' => ['enabled' => true],
        'new_pledges_enabled' => ['enabled' => true],
        'payouts_enabled' => ['enabled' => true],
        'refund_batches_enabled' => ['enabled' => true],
        'donation_reward_campaigns_enabled' => ['enabled' => true],
        'investment_campaigns_enabled' => ['enabled' => false],
    ],
    'classifications' => [
        'non_investment' => ['DONATION', 'REWARD', 'REWARD_BASED', 'PREORDER', 'PREPURCHASE', 'COMMUNITY_GRANT', 'PROJECT_SUPPORT'],
        'investment' => ['DEBT', 'REVENUE_SHARE', 'EQUITY', 'TOKEN_SALE', 'TOKENIZED_INVESTMENT', 'YIELD_PRODUCT'],
    ],
    'allowed_transitions' => [
        'DRAFT' => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED' => ['UNDER_REVIEW', 'NEEDS_INFORMATION', 'APPROVED', 'REJECTED'],
        'UNDER_REVIEW' => ['NEEDS_INFORMATION', 'APPROVED', 'REJECTED', 'SUSPENDED'],
        'NEEDS_INFORMATION' => ['SUBMITTED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['SCHEDULED', 'LIVE', 'SUSPENDED'],
        'SCHEDULED' => ['LIVE', 'SUSPENDED', 'CANCELLED'],
        'LIVE' => ['GOAL_REACHED', 'FUNDING_ENDED', 'SUSPENDED', 'CANCELLED'],
        'GOAL_REACHED' => ['FUNDING_ENDED', 'MILESTONE_EXECUTION'],
        'FUNDING_ENDED' => ['MILESTONE_EXECUTION', 'COMPLETED', 'FAILED', 'REFUNDING'],
        'MILESTONE_EXECUTION' => ['COMPLETED', 'FAILED', 'REFUNDING', 'SUSPENDED'],
        'SUSPENDED' => ['LIVE', 'REFUNDING', 'CANCELLED'],
        'CANCELLED' => ['REFUNDING'],
        'FAILED' => ['REFUNDING'],
        'REFUNDING' => ['REFUNDED'],
    ],
];
