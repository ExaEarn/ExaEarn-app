<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Athlete extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'sport',
        'country',
        'age',
        'position',
        'club',
        'profile_photo',
        'highlight_video',
        'performance_statistics',
        'identity_metadata',
        'identity_verified',
        'is_searchable',
    ];

    protected $casts = [
        'age' => 'integer',
        'performance_statistics' => 'array',
        'identity_metadata' => 'array',
        'identity_verified' => 'boolean',
        'is_searchable' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competitionEntries(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    public function leaderboard(): HasOne
    {
        return $this->hasOne(AthleteLeaderboard::class);
    }
}
