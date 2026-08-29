<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventDefinition extends Model
{
    protected $fillable = [
        'event_key',
        'product',
        'category',
        'priority',
        'severity',
        'default_channels',
        'user_configurable',
        'mandatory',
        'template_key',
        'template_version',
        'dedup_strategy',
        'retention_policy',
        'deep_link_policy',
        'activity_eligible',
        'status',
    ];

    protected $casts = [
        'default_channels' => 'array',
        'user_configurable' => 'boolean',
        'mandatory' => 'boolean',
        'activity_eligible' => 'boolean',
    ];
}
