<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperWebhookDelivery extends Model
{
    protected $fillable = [
        'delivery_uuid',
        'event_id',
        'endpoint_id',
        'event_type',
        'payload',
        'attempts',
        'last_status_code',
        'status',
        'last_error',
        'next_attempt_at',
        'delivered_at',
        'dead_lettered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(DeveloperWebhookEndpoint::class, 'endpoint_id');
    }
}
