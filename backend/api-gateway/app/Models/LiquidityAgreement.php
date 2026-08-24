<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityAgreement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'base_commitment' => 'decimal:18',
        'quote_commitment' => 'decimal:18',
        'spread_requirement_bps' => 'decimal:8',
        'depth_requirement' => 'decimal:18',
        'quote_presence_requirement' => 'decimal:8',
        'rebate_profile' => 'array',
        'metadata' => 'array',
        'effective_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
