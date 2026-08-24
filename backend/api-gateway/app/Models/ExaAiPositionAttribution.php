<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiPositionAttribution extends Model
{
    protected $table = 'exaai_position_attributions';

    protected $fillable = [
        'user_id',
        'portfolio_id',
        'session_id',
        'strategy_definition_id',
        'strategy_version_id',
        'product',
        'symbol',
        'asset',
        'side',
        'attributed_quantity',
        'remaining_quantity',
        'average_entry_price',
        'cost_basis',
        'realized_pnl',
        'unrealized_pnl',
        'fees',
        'sync_status',
        'metadata',
    ];

    protected $casts = [
        'attributed_quantity' => 'decimal:8',
        'remaining_quantity' => 'decimal:8',
        'average_entry_price' => 'decimal:8',
        'cost_basis' => 'decimal:8',
        'realized_pnl' => 'decimal:8',
        'unrealized_pnl' => 'decimal:8',
        'fees' => 'decimal:8',
        'metadata' => 'array',
    ];
}
