<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'scope',
        'in_app_enabled',
        'email_enabled',
        'push_enabled',
        'marketing_consent',
        'metadata',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'marketing_consent' => 'boolean',
        'metadata' => 'array',
    ];
}
