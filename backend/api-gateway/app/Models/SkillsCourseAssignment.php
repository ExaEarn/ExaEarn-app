<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsCourseAssignment extends Model
{
    protected $fillable = ['organization_id', 'program_id', 'course_id', 'assigned_to_user_id', 'assigned_by_user_id', 'status', 'progress_percentage', 'assigned_at', 'deadline_at', 'completed_at'];

    protected $casts = [
        'progress_percentage' => 'decimal:2',
        'assigned_at' => 'datetime',
        'deadline_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
