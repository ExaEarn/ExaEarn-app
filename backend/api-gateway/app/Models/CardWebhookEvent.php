<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardWebhookEvent extends Model
{
    protected $fillable = [
        'event_uuid',
        'provider',
        'provider_event_id',
        'event_type',
        'status',
        'payload',
        'headers',
        'processed_at',
        'failure_reason',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'processed_at' => 'datetime',
    ];
}
