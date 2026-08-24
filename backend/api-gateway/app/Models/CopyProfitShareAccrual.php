<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyProfitShareAccrual extends Model
{
    protected $fillable = [
        'accrual_id',
        'copy_relationship_id',
        'lead_trader_id',
        'follower_user_id',
        'asset',
        'eligible_profit',
        'profit_share_rate',
        'accrued_amount',
        'high_water_mark_before',
        'high_water_mark_after',
        'status',
        'ledger_reference',
        'metadata',
    ];

    protected $casts = [
        'eligible_profit' => 'decimal:18',
        'profit_share_rate' => 'decimal:8',
        'accrued_amount' => 'decimal:18',
        'high_water_mark_before' => 'decimal:18',
        'high_water_mark_after' => 'decimal:18',
        'metadata' => 'array',
    ];
}
