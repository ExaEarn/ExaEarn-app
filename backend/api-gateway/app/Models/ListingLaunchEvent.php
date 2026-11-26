<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingLaunchEvent extends Model
{
    public const SCHEDULED = 'SCHEDULED';
    public const DISPATCH_PENDING = 'DISPATCH_PENDING';
    public const DISPATCHED = 'DISPATCHED';
    public const RETRY_PENDING = 'RETRY_PENDING';
    public const EXECUTING = 'EXECUTING';
    public const SUCCEEDED = 'SUCCEEDED';
    public const FAILED = 'FAILED';
    public const BLOCKED = 'BLOCKED';
    public const CANCELLED = 'CANCELLED';
    public const PARTIALLY_FAILED = 'PARTIALLY_FAILED';
    public const LEGACY_UNVERIFIED = 'LEGACY_UNVERIFIED';

    public const DISPATCHABLE_STATES = [
        self::SCHEDULED,
        self::RETRY_PENDING,
        self::DISPATCH_PENDING,
        self::DISPATCHED,
    ];

    public const WORKER_CLAIMABLE_STATES = [
        self::DISPATCH_PENDING,
        self::DISPATCHED,
    ];

    public const TERMINAL_STATES = [
        self::SUCCEEDED,
        self::FAILED,
        self::BLOCKED,
        self::CANCELLED,
        self::PARTIALLY_FAILED,
        self::LEGACY_UNVERIFIED,
    ];

    public const CANCELLABLE_STATES = [
        self::SCHEDULED,
        self::RETRY_PENDING,
        self::DISPATCH_PENDING,
        self::DISPATCHED,
    ];

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
        'executed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'dispatch_attempt_count' => 'integer',
        'launch_scope' => 'array',
        'result' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ListingApplication::class, 'application_id');
    }
}
