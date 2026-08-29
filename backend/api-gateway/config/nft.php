<?php

declare(strict_types=1);

return [
    'default_chain' => env('NFT_DEFAULT_CHAIN', 'base'),
    'supported_standards' => ['ERC-721', 'ERC-1155'],
    'supported_settlement_assets' => ['EXA', 'USDT', 'USDC'],
    'marketplace_fee_bps' => (int) env('NFT_MARKETPLACE_FEE_BPS', 250),
    'max_royalty_bps' => (int) env('NFT_MAX_ROYALTY_BPS', 2000),
    'network_cost_exa' => env('NFT_NETWORK_COST_EXA', '0'),
    'external_chain_finality_required' => true,
    'media' => [
        'provider' => env('NFT_MEDIA_PROVIDER', 'local'),
        'mode' => env('NFT_MEDIA_MODE', env('APP_ENV') === 'production' ? 'PRODUCTION' : 'LOCAL_TEST'),
        'production_configured' => (bool) env('NFT_MEDIA_PRODUCTION_CONFIGURED', false),
        'disk' => env('NFT_MEDIA_PUBLIC_DISK', 'public'),
        'private_disk' => env('NFT_MEDIA_PRIVATE_DISK', 'local'),
        'max_size_bytes' => (int) env('NFT_MEDIA_MAX_BYTES', 20971520),
        'reuse_duplicates' => true,
        'supported_types' => ['IMAGE', 'VIDEO', 'AUDIO', 'ANIMATION', 'DOCUMENT', 'THUMBNAIL', 'COLLECTION_LOGO', 'COLLECTION_BANNER'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mp3', 'json', 'pdf'],
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'audio/mpeg', 'application/json', 'application/pdf'],
        'size_limits' => [
            'image' => (int) env('NFT_MEDIA_IMAGE_MAX_BYTES', 10485760),
            'thumbnail' => (int) env('NFT_MEDIA_THUMBNAIL_MAX_BYTES', 5242880),
            'collection_logo' => (int) env('NFT_MEDIA_LOGO_MAX_BYTES', 5242880),
            'collection_banner' => (int) env('NFT_MEDIA_BANNER_MAX_BYTES', 10485760),
            'video' => (int) env('NFT_MEDIA_VIDEO_MAX_BYTES', 52428800),
            'audio' => (int) env('NFT_MEDIA_AUDIO_MAX_BYTES', 20971520),
            'animation' => (int) env('NFT_MEDIA_ANIMATION_MAX_BYTES', 52428800),
            'document' => (int) env('NFT_MEDIA_DOCUMENT_MAX_BYTES', 10485760),
        ],
    ],
];
