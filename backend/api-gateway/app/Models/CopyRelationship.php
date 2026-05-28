<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyRelationship extends Model
{
    protected $fillable = [
        'follower_id',
        'trader_id',
        'amount_allocated',
        'risk_level',
        'status',
    ];

    protected $casts = [
        'amount_allocated' => 'decimal:8',
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
