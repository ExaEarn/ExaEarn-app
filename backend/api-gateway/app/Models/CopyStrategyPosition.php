<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyStrategyPosition extends Model
{
    protected $fillable = [
        'strategy_position_uuid',
        'copy_relationship_id',
        'lead_trader_id',
        'follower_user_id',
        'product',
        'symbol',
        'asset',
        'side',
        'attributed_quantity',
        'remaining_quantity',
        'average_entry_price',
        'attributed_cost_basis',
        'realized_pnl',
        'fees',
        'sync_status',
        'metadata',
    ];

    protected $casts = [
        'attributed_quantity' => 'decimal:18',
        'remaining_quantity' => 'decimal:18',
        'average_entry_price' => 'decimal:18',
        'attributed_cost_basis' => 'decimal:18',
        'realized_pnl' => 'decimal:18',
        'fees' => 'decimal:18',
        'metadata' => 'array',
    ];
}
