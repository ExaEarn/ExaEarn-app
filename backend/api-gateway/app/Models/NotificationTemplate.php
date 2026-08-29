<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'template_key',
        'version',
        'channel',
        'locale',
        'title',
        'body',
        'variables',
        'status',
        'effective_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'effective_at' => 'datetime',
    ];
}
