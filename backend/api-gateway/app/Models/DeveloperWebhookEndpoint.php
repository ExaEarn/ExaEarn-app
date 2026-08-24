<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperWebhookEndpoint extends Model
{
    protected $fillable = [
        'endpoint_uuid',
        'user_id',
        'project_id',
        'url',
        'status',
        'events',
        'encrypted_secret',
        'secret_rotated_at',
        'last_delivered_at',
    ];

    protected $casts = [
        'events' => 'array',
        'secret_rotated_at' => 'datetime',
        'last_delivered_at' => 'datetime',
    ];
}
