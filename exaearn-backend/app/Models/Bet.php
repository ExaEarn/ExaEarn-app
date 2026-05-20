<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bet extends Model
{
    protected $fillable = [
        'pool_id',
        'user_id',
        'wallet_address',
        'bet_option',
        'bet_amount_eth',
        'entry_tx_hash',
        'status',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'bet_amount_eth' => 'decimal:8',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(BettingPool::class, 'pool_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
