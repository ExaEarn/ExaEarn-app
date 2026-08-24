<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotExecutionLeg extends Model
{
    protected $fillable = [
        'execution_leg_id',
        'order_id',
        'market_id',
        'market_symbol',
        'venue',
        'liquidity_source',
        'side',
        'quantity',
        'price',
        'quote_amount',
        'fee_amount',
        'fee_asset',
        'external_execution_id',
        'ledger_reference',
        'status',
        'metadata',
        'executed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:18',
        'price' => 'decimal:18',
        'quote_amount' => 'decimal:18',
        'fee_amount' => 'decimal:18',
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];
}
