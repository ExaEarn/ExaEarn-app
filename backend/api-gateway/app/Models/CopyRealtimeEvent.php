<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyRealtimeEvent extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'stream',
        'sequence',
        'event_type',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sequence' => 'integer',
        'published_at' => 'datetime',
    ];
}
