<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotteryEntry extends Model
{
    protected $fillable = [
        'game_id',
        'user_id',
        'wallet_address',
        'entry_tx_hash',
        'entry_amount_eth',
        'status',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'entry_amount_eth' => 'decimal:8',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(LotteryGame::class, 'game_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
