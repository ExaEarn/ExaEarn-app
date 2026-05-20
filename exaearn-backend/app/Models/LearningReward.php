<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningReward extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'reward_amount',
        'reward_token',
        'status',
        'reward_id',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(UserReward::class, 'reward_id');
    }
}
