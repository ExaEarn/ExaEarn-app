<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingOperationalAuditLog extends Model
{
    protected $fillable = [
        'audit_id', 'admin_id', 'actor_type', 'action', 'scope', 'scope_key',
        'before_state', 'after_state', 'reason', 'correlation_id', 'performed_at',
    ];

    protected $casts = ['before_state' => 'array', 'after_state' => 'array', 'performed_at' => 'datetime'];
}
