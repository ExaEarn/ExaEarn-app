<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'reward_amount',
        'reward_token',
        'activity_type',
        'level',
        'status',
        'event_key',
        'transaction_id',
        'metadata',
        'approved_at',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:8',
        'level' => 'integer',
        'metadata' => 'array',
        'approved_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
