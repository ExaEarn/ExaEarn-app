<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LotteryGame extends Model
{
    protected $fillable = [
        'game_uuid',
        'contract_round_id',
        'name',
        'entry_fee_eth',
        'max_players',
        'current_players',
        'jackpot_amount_eth',
        'trigger_type',
        'draw_at',
        'rolling_interval_seconds',
        'status',
        'contract_address',
        'creation_tx_hash',
        'metadata',
    ];

    protected $casts = [
        'entry_fee_eth' => 'decimal:8',
        'jackpot_amount_eth' => 'decimal:8',
        'draw_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LotteryEntry::class, 'game_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LotteryResult::class, 'game_id');
    }
}
