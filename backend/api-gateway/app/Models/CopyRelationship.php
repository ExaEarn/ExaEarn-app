<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyRelationship extends Model
{
    protected $fillable = [
        'relationship_uuid',
        'follower_id',
        'trader_id',
        'amount_allocated',
        'copy_available',
        'copy_locked',
        'copy_pnl',
        'risk_level',
        'product_scope',
        'copy_mode',
        'fixed_amount_per_trade',
        'fixed_ratio',
        'max_amount_per_trade',
        'max_daily_loss',
        'max_drawdown',
        'max_leverage',
        'margin_preference',
        'allowed_symbols',
        'high_water_mark',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount_allocated' => 'decimal:18',
        'copy_available' => 'decimal:18',
        'copy_locked' => 'decimal:18',
        'copy_pnl' => 'decimal:18',
        'fixed_amount_per_trade' => 'decimal:18',
        'fixed_ratio' => 'decimal:8',
        'max_amount_per_trade' => 'decimal:18',
        'max_daily_loss' => 'decimal:18',
        'max_drawdown' => 'decimal:8',
        'max_leverage' => 'integer',
        'allowed_symbols' => 'array',
        'high_water_mark' => 'decimal:18',
        'metadata' => 'array',
    ];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function trader(): BelongsTo
    {
        return $this->belongsTo(Trader::class, 'trader_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }
}
