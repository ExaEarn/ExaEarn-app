<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trader extends Model
{
    protected $fillable = [
        'lead_trader_uuid',
        'user_id',
        'display_name',
        'bio',
        'is_master_trader',
        'status',
        'supported_products',
        'performance_score',
        'risk_score',
        'followers_count',
        'copy_aum',
        'profit_share_rate',
        'approved_at',
        'metadata',
    ];

    protected $casts = [
        'is_master_trader' => 'boolean',
        'supported_products' => 'array',
        'performance_score' => 'decimal:4',
        'risk_score' => 'decimal:4',
        'copy_aum' => 'decimal:18',
        'profit_share_rate' => 'decimal:8',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(CopyRelationship::class, 'trader_id');
    }

    public function incrementFollowers(): void
    {
        $this->increment('followers_count');
    }

    public function decrementFollowers(): void
    {
        if ((int) $this->followers_count > 0) {
            $this->decrement('followers_count');
        }
    }
}
