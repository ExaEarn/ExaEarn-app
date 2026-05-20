<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserStake extends Model
{
    protected $fillable = [
        'user_id',
        'pool_id',
        'amount',
        'compounded_amount',
        'lock_start',
        'lock_end',
        'last_reward_at',
        'auto_compound',
        'status',
        'tx_hash',
        'unstake_tx_hash',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'compounded_amount' => 'decimal:8',
        'lock_start' => 'datetime',
        'lock_end' => 'datetime',
        'last_reward_at' => 'datetime',
        'auto_compound' => 'boolean',
        'metadata' => 'array',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(StakingPool::class, 'pool_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(StakingReward::class, 'stake_id');
    }
}
