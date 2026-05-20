<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    protected $fillable = [
        'user_id',
        'points_used',
        'usdt_value',
        'redemption_type',
        'status',
        'reviewed_by',
        'metadata',
    ];

    protected $casts = [
        'points_used' => 'integer',
        'usdt_value' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
