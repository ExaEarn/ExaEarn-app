<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpotCutoverJournal extends Model
{
    protected $fillable = [
        'cutover_id',
        'market_id',
        'market_symbol',
        'previous_mode',
        'new_mode',
        'previous_state',
        'new_state',
        'status',
        'reason',
        'initiated_by_type',
        'initiated_by_id',
        'approved_by_id',
        'started_at',
        'completed_at',
        'engine_owner',
        'fencing_generation',
        'last_legacy_sequence',
        'new_engine_sequence',
        'snapshot_id',
        'reconciliation_result',
        'open_orders_before',
        'open_orders_after',
        'reservations_before',
        'reservations_after',
        'rollback_reference',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reconciliation_result' => 'array',
        'metadata' => 'array',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
