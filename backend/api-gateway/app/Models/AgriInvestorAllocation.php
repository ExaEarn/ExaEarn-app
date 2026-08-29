<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgriInvestorAllocation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gross_amount' => 'decimal:18',
        'fee_amount' => 'decimal:18',
        'net_amount' => 'decimal:18',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(AgriHarvestSettlement::class, 'harvest_settlement_id');
    }
}
