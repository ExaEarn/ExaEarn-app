<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExapointBalance extends Model
{
    protected $fillable = [
        'user_id',
        'available_points',
        'locked_points',
        'total_earned',
        'total_spent',
    ];

    protected $casts = [
        'available_points' => 'decimal:8',
        'locked_points' => 'decimal:8',
        'total_earned' => 'decimal:8',
        'total_spent' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
