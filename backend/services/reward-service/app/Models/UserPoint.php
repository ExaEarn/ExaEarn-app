<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPoint extends Model
{
    protected $fillable = [
        'user_id',
        'total_points',
        'available_points',
        'redeemed_points',
        'lifetime_points',
    ];

    protected $casts = [
        'total_points' => 'integer',
        'available_points' => 'integer',
        'redeemed_points' => 'integer',
        'lifetime_points' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
