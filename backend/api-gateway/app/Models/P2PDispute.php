<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2PDispute extends Model
{
    protected $table = 'p2p_disputes';

    protected $fillable = [
        'trade_id',
        'opened_by',
        'resolved_by',
        'reason',
        'evidence',
        'status',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(P2PTrade::class, 'trade_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
