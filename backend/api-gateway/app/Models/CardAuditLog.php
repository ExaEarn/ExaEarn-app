<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardAuditLog extends Model
{
    protected $fillable = [
        'audit_uuid',
        'user_id',
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
