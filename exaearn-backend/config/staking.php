<?php

declare(strict_types=1);

return [
    'stake_asset' => env('STAKING_ASSET', 'XRP'),
    'reward_token' => env('STAKING_REWARD_TOKEN', 'EXA'),
    'seconds_per_year' => 31536000,
    'min_reward_payout' => env('STAKING_MIN_REWARD_PAYOUT', '0.00000001'),
];
