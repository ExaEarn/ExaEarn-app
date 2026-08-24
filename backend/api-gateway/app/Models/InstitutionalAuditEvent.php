<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalAuditEvent extends Model
{
    protected $fillable = ['event_uuid', 'institution_id', 'subaccount_id', 'actor_type', 'actor_id', 'action', 'resource_type', 'resource_id', 'before', 'after', 'reason', 'ip_address'];

    protected $casts = ['before' => 'array', 'after' => 'array'];
}
