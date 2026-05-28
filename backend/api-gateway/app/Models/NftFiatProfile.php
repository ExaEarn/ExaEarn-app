<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NftFiatProfile extends Model
{
    protected $fillable = [
        'nft_id', 'user_id', 'daily_limit_usd', 'withdrawal_fee_bps', 'spread_bps', 'speed_tier', 'status', 'metadata',
    ];

    protected $casts = [
        'daily_limit_usd' => 'decimal:2',
        'withdrawal_fee_bps' => 'decimal:2',
        'spread_bps' => 'decimal:2',
        'metadata' => 'array',
    ];
}
