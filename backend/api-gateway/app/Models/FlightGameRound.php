<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlightGameRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_uuid',
        'round_number',
        'status',
        'round_state',
        'mode',
        'asset',
        'fairness_version',
        'server_seed_hash',
        'server_seed',
        'client_seed',
        'nonce',
        'crash_multiplier',
        'growth_rate',
        'betting_opens_at',
        'betting_closes_at',
        'locked_at',
        'starts_at',
        'crashes_at',
        'ended_at',
        'settled_at',
        'manual_review_at',
        'metadata',
    ];

    protected $casts = [
        'crash_multiplier' => 'decimal:8',
        'growth_rate' => 'decimal:8',
        'betting_opens_at' => 'datetime',
        'betting_closes_at' => 'datetime',
        'locked_at' => 'datetime',
        'starts_at' => 'datetime',
        'crashes_at' => 'datetime',
        'ended_at' => 'datetime',
        'settled_at' => 'datetime',
        'manual_review_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(FlightGameBet::class, 'round_id');
    }
}
