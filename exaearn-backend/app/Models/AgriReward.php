<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgriReward extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'investment_id',
        'activity_type',
        'reward_amount',
        'status',
        'reward_reference',
        'metadata',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(FarmingProject::class, 'project_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(FarmInvestment::class, 'investment_id');
    }
}
