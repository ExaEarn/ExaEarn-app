<?php

return [
    'types' => ['CAMPAIGN', 'PLATFORM_UPDATE', 'MARKET_NEWS', 'MARKET_INSIGHT', 'EXAAI_INSIGHT', 'EARN_OPPORTUNITY', 'CROWDFUNDING_UPDATE', 'PRODUCT_LAUNCH', 'EDUCATION', 'REWARD', 'PROMOTION', 'SECURITY_INFORMATION', 'MAINTENANCE', 'ECOSYSTEM_DISCOVERY'],
    'sources' => ['ADMIN', 'SYSTEM', 'MARKET', 'EXAAI', 'PRODUCT_EVENT', 'EXTERNAL'],
    'routes' => ['trade', 'market', 'cryptoMarkets', 'futures', 'swap', 'p2pMarketplace', 'aiAssistant', 'staking', 'crowdfunding', 'agriculture', 'edtech', 'exacard', 'nftMarketplace', 'game', 'giftcard', 'rewards', 'campaigns', 'settings'],
    'product_compliance_map' => ['SPOT' => 'SPOT', 'FUTURES' => 'FUTURES', 'P2P' => 'P2P', 'EXAAI' => 'EXAAI', 'EARN' => 'STAKING', 'EXACARD' => 'EXACARD', 'CROWDFUNDING' => 'CROWDFUNDING', 'AGRITECH' => 'AGRITECH', 'GAMES' => 'GAMES'],
    'weights' => ['primary_interest' => 32, 'secondary_interest' => 18, 'product' => 22, 'asset' => 18, 'experience_mode' => 8, 'freshness' => 20, 'priority_scale' => 0.5, 'saved' => 16],
    'dashboard_limit' => 5,
    'feed_limit' => 20,
    'event_registry' => [
        'earn.product.activated' => ['type' => 'EARN_OPPORTUNITY', 'product' => 'EARN', 'route' => 'staking', 'badge' => 'EARN OPPORTUNITY', 'cta' => 'Explore Earn'],
        'crowdfunding.campaign.published' => ['type' => 'CROWDFUNDING_UPDATE', 'product' => 'CROWDFUNDING', 'route' => 'crowdfunding', 'badge' => 'CROWDFUNDING', 'cta' => 'View Campaign'],
        'crowdfunding.milestone.reached' => ['type' => 'CROWDFUNDING_UPDATE', 'product' => 'CROWDFUNDING', 'route' => 'crowdfunding', 'badge' => 'CAMPAIGN UPDATE', 'cta' => 'See Update'],
        'exaskills.course.published' => ['type' => 'EDUCATION', 'product' => 'EXASKILLS', 'route' => 'edtech', 'badge' => 'LEARNING', 'cta' => 'Start Learning'],
        'market.listing.activated' => ['type' => 'PRODUCT_LAUNCH', 'product' => 'SPOT', 'route' => 'market', 'badge' => 'NEW MARKET', 'cta' => 'View Market'],
        'exacard.availability.updated' => ['type' => 'PRODUCT_LAUNCH', 'product' => 'EXACARD', 'route' => 'exacard', 'badge' => 'EXACARD', 'cta' => 'Explore ExaCard'],
        'product.feature.launched' => ['type' => 'PRODUCT_LAUNCH', 'route' => 'campaigns', 'badge' => 'EXAEARN UPDATE', 'cta' => 'View Details'],
        'promotion.started' => ['type' => 'PROMOTION', 'route' => 'campaigns', 'badge' => 'PROMOTION', 'cta' => 'View Details'],
    ],
];
