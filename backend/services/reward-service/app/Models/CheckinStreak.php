<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinStreak extends Model
{
    protected $fillable = [
        'user_id',
        'current_streak',
        'highest_streak',
        'last_checkin_date',
    ];

    protected $casts = [
        'current_streak' => 'integer',
        'highest_streak' => 'integer',
        'last_checkin_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
