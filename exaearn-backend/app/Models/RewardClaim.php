<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardClaim extends Model
{
    protected $fillable = [
        'user_id',
        'reward_id',
        'claimed_amount',
        'wallet_address',
        'tx_hash',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'claimed_amount' => 'decimal:8',
        'timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function reward(): BelongsTo
    {
        return $this->belongsTo(UserReward::class, 'reward_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
