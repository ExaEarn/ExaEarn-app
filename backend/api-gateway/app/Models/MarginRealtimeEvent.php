<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarginRealtimeEvent extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'margin_account_id',
        'sequence',
        'event',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];

    public function marginAccount(): BelongsTo
    {
        return $this->belongsTo(MarginAccount::class);
    }
}
