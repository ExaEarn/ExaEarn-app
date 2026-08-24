<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiRealtimeEvent extends Model
{
    protected $table = 'exaai_realtime_events';

    protected $fillable = [
        'user_id',
        'stream',
        'sequence',
        'event_type',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];
}
