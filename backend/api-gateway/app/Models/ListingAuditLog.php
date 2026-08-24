<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingAuditLog extends Model
{
    protected $fillable = ['audit_uuid', 'application_id', 'actor_type', 'actor_id', 'action', 'resource_type', 'resource_id', 'before', 'after', 'reason', 'ip_address', 'user_agent'];

    protected $casts = ['before' => 'array', 'after' => 'array'];
}
