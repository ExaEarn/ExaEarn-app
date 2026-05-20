<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserReward extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type',
        'activity_value',
        'reward_amount',
        'reward_token',
        'status',
        'activity_key',
        'validated_at',
        'distributed_at',
        'distribution_reference',
        'metadata',
    ];

    protected $casts = [
        'activity_value' => 'decimal:8',
        'reward_amount' => 'decimal:8',
        'validated_at' => 'datetime',
        'distributed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claim(): HasOne
    {
        return $this->hasOne(RewardClaim::class, 'reward_id');
    }
}
