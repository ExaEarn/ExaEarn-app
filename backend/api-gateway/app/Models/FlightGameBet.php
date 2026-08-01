<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightGameBet extends Model
{
    use HasFactory;

    protected $fillable = [
        'bet_uuid',
        'user_id',
        'round_id',
        'panel_slot',
        'mode',
        'asset',
        'stake',
        'auto_cashout',
        'status',
        'cashout_multiplier',
        'payout',
        'profit',
        'idempotency_key',
        'ledger_reference',
        'placed_at',
        'cashed_out_at',
        'settled_at',
        'metadata',
    ];

    protected $casts = [
        'stake' => 'decimal:18',
        'auto_cashout' => 'decimal:8',
        'cashout_multiplier' => 'decimal:8',
        'payout' => 'decimal:18',
        'profit' => 'decimal:18',
        'placed_at' => 'datetime',
        'cashed_out_at' => 'datetime',
        'settled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(FlightGameRound::class, 'round_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}