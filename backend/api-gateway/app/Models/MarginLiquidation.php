<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarginLiquidation extends Model
{
    protected $fillable = [
        'liquidation_id',
        'margin_account_id',
        'user_id',
        'mode',
        'market_symbol',
        'status',
        'trigger_health_factor',
        'assets_sold',
        'debt_repaid',
        'liquidation_fee',
        'reserve_impact',
        'bad_debt_amount',
        'ledger_reference',
        'metadata',
    ];

    protected $casts = [
        'trigger_health_factor' => 'decimal:18',
        'assets_sold' => 'array',
        'debt_repaid' => 'array',
        'liquidation_fee' => 'decimal:18',
        'reserve_impact' => 'decimal:18',
        'bad_debt_amount' => 'decimal:18',
        'metadata' => 'array',
    ];

    public function marginAccount(): BelongsTo
    {
        return $this->belongsTo(MarginAccount::class);
    }
}
