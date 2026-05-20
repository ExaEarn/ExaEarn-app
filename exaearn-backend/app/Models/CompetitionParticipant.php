<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionParticipant extends Model
{
    protected $fillable = [
        'competition_id',
        'athlete_id',
        'score',
        'ranking',
        'community_votes',
        'status',
        'verification_metadata',
        'verified_at',
    ];

    protected $casts = [
        'score' => 'decimal:8',
        'ranking' => 'integer',
        'community_votes' => 'integer',
        'verification_metadata' => 'array',
        'verified_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
