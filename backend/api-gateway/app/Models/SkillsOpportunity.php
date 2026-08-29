<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillsOpportunity extends Model
{
    protected $fillable = ['company_user_id', 'organization_id', 'company_name', 'title', 'slug', 'type', 'description', 'required_skills', 'preferred_skills', 'required_credentials', 'compensation_label', 'location_type', 'employment_type', 'remote_policy', 'experience_level', 'status', 'review_status', 'deadline_at', 'published_at', 'paused_at', 'closed_at', 'moderation'];

    protected $casts = [
        'required_skills' => 'array',
        'preferred_skills' => 'array',
        'required_credentials' => 'array',
        'moderation' => 'array',
        'deadline_at' => 'datetime',
        'published_at' => 'datetime',
        'paused_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(SkillsApplication::class, 'opportunity_id');
    }
}
