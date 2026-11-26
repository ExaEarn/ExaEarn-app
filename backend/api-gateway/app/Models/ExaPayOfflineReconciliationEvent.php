<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ExaPayOfflineReconciliationEvent extends Model
{
    public $timestamps = false;

    protected $table = 'exapay_offline_reconciliation_events';

    protected $fillable = ['claim_id', 'sequence', 'from_state', 'to_state', 'event_type', 'previous_digest', 'actor_type', 'actor_id', 'event_digest', 'metadata', 'created_at'];

    protected $casts = ['sequence' => 'integer', 'metadata' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Offline reconciliation audit events are append-only.'));
        static::deleting(static fn () => throw new LogicException('Offline reconciliation audit events are append-only.'));
    }
}
