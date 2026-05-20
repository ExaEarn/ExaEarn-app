<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StakingReward extends Model
{
    protected $fillable = [
        'user_id',
        'stake_id',
        'reward_amount',
        'reward_token',
        'claimed',
        'claimed_at',
        'tx_hash',
        'metadata',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:8',
        'claimed' => 'boolean',
        'claimed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function stake(): BelongsTo
    {
        return $this->belongsTo(UserStake::class, 'stake_id');
    }
}
