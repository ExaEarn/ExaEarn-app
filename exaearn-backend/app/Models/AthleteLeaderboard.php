<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteLeaderboard extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'athlete_id',
        'sport',
        'competition_wins',
        'performance_score',
        'community_votes',
        'sponsorship_count',
        'sponsorship_total',
        'updated_at',
    ];

    protected $casts = [
        'competition_wins' => 'integer',
        'performance_score' => 'decimal:8',
        'community_votes' => 'integer',
        'sponsorship_count' => 'integer',
        'sponsorship_total' => 'decimal:8',
        'updated_at' => 'datetime',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
