<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardActivity extends Model
{
    protected $fillable = [
        'activity_type',
        'reward_rate',
        'daily_limit',
        'status',
        'mode',
        'min_activity_value',
        'metadata',
    ];

    protected $casts = [
        'reward_rate' => 'decimal:8',
        'daily_limit' => 'decimal:8',
        'min_activity_value' => 'decimal:8',
        'metadata' => 'array',
    ];
}
