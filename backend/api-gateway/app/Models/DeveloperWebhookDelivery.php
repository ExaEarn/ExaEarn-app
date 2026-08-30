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
        'project_id','environment',
        'event_type',
        'payload',
        'attempts',
        'last_status_code',
        'status',
        'claim_token','claimed_at','claim_expires_at',
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
        'claimed_at'=>'datetime','claim_expires_at'=>'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(DeveloperWebhookEndpoint::class, 'endpoint_id');
    }
}
