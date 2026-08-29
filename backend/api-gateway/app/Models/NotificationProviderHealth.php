<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationProviderHealth extends Model
{
    protected $table = 'notification_provider_health';

    protected $fillable = [
        'provider',
        'channel',
        'status',
        'success_count',
        'failure_count',
        'last_success_at',
        'last_failure_at',
        'metadata',
    ];

    protected $casts = [
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'metadata' => 'array',
    ];
}
