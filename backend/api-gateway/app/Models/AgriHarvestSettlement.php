<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriHarvestSettlement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gross_revenue' => 'decimal:18',
        'verified_costs' => 'decimal:18',
        'platform_fee' => 'decimal:18',
        'net_distributable' => 'decimal:18',
        'verified_at' => 'datetime',
        'settled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FarmingProject::class, 'project_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(AgriInvestorAllocation::class, 'harvest_settlement_id');
    }
}
