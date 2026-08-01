<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillsChallenge extends Model
{
    protected $fillable = ['sponsor_user_id', 'title', 'slug', 'sponsor_name', 'description', 'required_skills', 'reward_amount', 'reward_asset', 'difficulty', 'status', 'deadline_at', 'metadata'];

    protected $casts = [
        'required_skills' => 'array',
        'reward_amount' => 'decimal:8',
        'deadline_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(SkillsChallengeSubmission::class, 'challenge_id');
    }
}
