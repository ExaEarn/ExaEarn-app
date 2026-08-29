<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsTrainingProgram extends Model
{
    protected $fillable = ['organization_id', 'created_by', 'title', 'description', 'required_course_ids', 'optional_course_ids', 'status', 'deadline_at'];

    protected $casts = [
        'required_course_ids' => 'array',
        'optional_course_ids' => 'array',
        'deadline_at' => 'datetime',
    ];
}
