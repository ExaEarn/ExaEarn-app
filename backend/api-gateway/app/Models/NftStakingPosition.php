<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftStakingPosition extends Model
{
    protected $fillable = [
        'nft_id', 'user_id', 'staked_amount_exa', 'reward_rate_bps', 'platform_commission_bps',
        'accumulated_rewards_exa', 'status', 'started_at', 'last_claimed_at', 'metadata',
    ];

    protected $casts = [
        'staked_amount_exa' => 'decimal:8',
        'reward_rate_bps' => 'decimal:2',
        'platform_commission_bps' => 'decimal:2',
        'accumulated_rewards_exa' => 'decimal:8',
        'started_at' => 'datetime',
        'last_claimed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
