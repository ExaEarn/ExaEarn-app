<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCheckIn extends Model
{
    protected $fillable = [
        'user_id',
        'last_check_in_at',
        'streak',
        'next_check_in_at',
    ];

    protected $casts = [
        'last_check_in_at' => 'datetime',
        'next_check_in_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
