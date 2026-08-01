<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillsOpportunity extends Model
{
    protected $fillable = ['company_user_id', 'company_name', 'title', 'slug', 'type', 'description', 'required_skills', 'compensation_label', 'location_type', 'status', 'deadline_at'];

    protected $casts = [
        'required_skills' => 'array',
        'deadline_at' => 'datetime',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(SkillsApplication::class, 'opportunity_id');
    }
}
