<?php

declare(strict_types=1);

return [
    'image_max_kb' => (int) env('PROFILE_IMAGE_MAX_KB', 5120),
    'image_disk' => env('PROFILE_IMAGE_DISK', 'local'),
    'image_directory' => 'private/profile-images',
    'standard_size' => 512,
    'thumbnail_size' => 128,
    'webp_quality' => 86,
    'require_review' => filter_var(env('PROFILE_REQUIRE_REVIEW', false), FILTER_VALIDATE_BOOLEAN),
    'display_types' => ['initials', 'avatar', 'custom_image'],
    'visibility' => ['self', 'p2p', 'public'],
    'statuses' => ['none', 'pending_review', 'approved', 'rejected', 'removed'],
    'avatars' => [
        ['id' => 'classic-gold', 'category' => 'Classic', 'name' => 'Gold Signal', 'accent' => '#d4af37', 'background' => 'linear-gradient(135deg,#18110a,#d4af37)', 'mark' => 'EX'],
        ['id' => 'classic-onyx', 'category' => 'Classic', 'name' => 'Onyx Vault', 'accent' => '#f8e7ad', 'background' => 'linear-gradient(135deg,#07070a,#34343d)', 'mark' => 'EA'],
        ['id' => 'web3-orbit', 'category' => 'Web3', 'name' => 'Orbit Node', 'accent' => '#7dd3fc', 'background' => 'radial-gradient(circle at 30% 25%,#7dd3fc,transparent 28%),linear-gradient(135deg,#111827,#312e81)', 'mark' => '◎'],
        ['id' => 'web3-ledger', 'category' => 'Web3', 'name' => 'Ledger Mesh', 'accent' => '#a78bfa', 'background' => 'linear-gradient(135deg,#0f172a,#4c1d95)', 'mark' => '◇'],
        ['id' => 'minimal-slate', 'category' => 'Minimal', 'name' => 'Slate Initial', 'accent' => '#e5e7eb', 'background' => 'linear-gradient(135deg,#111827,#374151)', 'mark' => 'E'],
        ['id' => 'minimal-auric', 'category' => 'Minimal', 'name' => 'Auric Dot', 'accent' => '#d4af37', 'background' => 'linear-gradient(135deg,#0b0b0d,#1f2937)', 'mark' => '•'],
        ['id' => 'future-neon', 'category' => 'Futuristic', 'name' => 'Neon Vector', 'accent' => '#22d3ee', 'background' => 'linear-gradient(135deg,#050816,#0e7490)', 'mark' => '△'],
        ['id' => 'future-quantum', 'category' => 'Futuristic', 'name' => 'Quantum Core', 'accent' => '#f59e0b', 'background' => 'linear-gradient(135deg,#120817,#7c2d12)', 'mark' => '✦'],
        ['id' => 'exaearn-crown', 'category' => 'ExaEarn', 'name' => 'ExaEarn Crown', 'accent' => '#d4af37', 'background' => 'linear-gradient(135deg,#060606,#30240a,#d4af37)', 'mark' => 'EXAEARN'],
        ['id' => 'exaearn-prime', 'category' => 'ExaEarn', 'name' => 'ExaEarn Prime', 'accent' => '#ffffff', 'background' => 'linear-gradient(135deg,#0a0512,#d4af37)', 'mark' => 'E'],
    ],
];