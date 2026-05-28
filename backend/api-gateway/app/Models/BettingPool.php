<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BettingPool extends Model
{
    protected $fillable = [
        'pool_uuid',
        'contract_pool_id',
        'event_name',
        'bet_options',
        'entry_fee_eth',
        'status',
        'winning_option',
        'locking_at',
        'contract_address',
        'creation_tx_hash',
        'metadata',
    ];

    protected $casts = [
        'bet_options' => 'array',
        'entry_fee_eth' => 'decimal:8',
        'locking_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class, 'pool_id');
    }
}
