<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingCircuitBreaker extends Model
{
    public const NORMAL = 'NORMAL';
    public const WARNING = 'WARNING';
    public const RESTRICTED = 'RESTRICTED';
    public const CANCEL_ONLY = 'CANCEL_ONLY';
    public const REDUCE_ONLY = 'REDUCE_ONLY';
    public const PAUSED = 'PAUSED';
    public const EMERGENCY_STOP = 'EMERGENCY_STOP';

    protected $fillable = [
        'breaker_id', 'scope', 'scope_key', 'state', 'reason_code', 'reason',
        'incident_id', 'changed_by_admin_id', 'activated_at', 'cleared_at', 'metadata',
    ];

    protected $casts = ['metadata' => 'array', 'activated_at' => 'datetime', 'cleared_at' => 'datetime'];
}
