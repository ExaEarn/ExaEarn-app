<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralLeaderboard extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'timeframe',
        'period_start',
        'period_end',
        'total_invites',
        'active_invites',
        'total_rewards',
        'updated_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'total_invites' => 'integer',
        'active_invites' => 'integer',
        'total_rewards' => 'decimal:8',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
