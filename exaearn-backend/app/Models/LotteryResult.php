<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotteryResult extends Model
{
    protected $fillable = [
        'game_id',
        'winner_wallet',
        'jackpot_amount_eth',
        'tx_hash',
        'draw_time',
        'metadata',
    ];

    protected $casts = [
        'jackpot_amount_eth' => 'decimal:8',
        'draw_time' => 'datetime',
        'metadata' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(LotteryGame::class, 'game_id');
    }
}
