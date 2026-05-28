<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2PRating extends Model
{
    protected $table = 'p2p_ratings';

    protected $fillable = [
        'trade_id',
        'rater_user_id',
        'rated_user_id',
        'score',
        'comment',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(P2PTrade::class, 'trade_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }

    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }
}
