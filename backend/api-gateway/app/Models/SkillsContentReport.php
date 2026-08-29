<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsContentReport extends Model
{
    protected $fillable = ['reporter_user_id', 'course_id', 'lesson_id', 'reason', 'description', 'status', 'reviewed_by_admin_id', 'reviewed_at', 'metadata'];

    protected $casts = ['reviewed_at' => 'datetime', 'metadata' => 'array'];
}
