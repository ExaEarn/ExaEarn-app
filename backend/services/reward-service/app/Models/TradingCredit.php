<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingCredit extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'source',
        'locked',
        'withdrawable_profit',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'locked' => 'boolean',
        'withdrawable_profit' => 'decimal:8',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
