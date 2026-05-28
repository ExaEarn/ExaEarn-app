<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StakingPool extends Model
{
    protected $fillable = [
        'asset',
        'reward_token',
        'contract_pool_id',
        'lock_period',
        'reward_rate',
        'reward_multiplier',
        'pool_size',
        'total_staked',
        'status',
        'metadata',
    ];

    protected $casts = [
        'reward_rate' => 'decimal:8',
        'reward_multiplier' => 'decimal:8',
        'pool_size' => 'decimal:8',
        'total_staked' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function stakes(): HasMany
    {
        return $this->hasMany(UserStake::class, 'pool_id');
    }
}
