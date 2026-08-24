<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketMakerInventorySnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'current_base_inventory' => 'decimal:18',
        'current_quote_inventory' => 'decimal:18',
        'target_base_inventory' => 'decimal:18',
        'target_quote_inventory' => 'decimal:18',
        'inventory_imbalance' => 'decimal:18',
        'inventory_utilization' => 'decimal:8',
        'net_delta' => 'decimal:18',
        'max_exposure' => 'decimal:18',
        'metadata' => 'array',
        'measured_at' => 'datetime',
    ];
}
