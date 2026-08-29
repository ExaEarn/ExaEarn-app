<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsLessonProgress extends Model
{
    protected $fillable = ['user_id', 'course_id', 'lesson_id', 'status', 'watch_seconds', 'started_at', 'completed_at', 'metadata'];

    protected $casts = ['watch_seconds' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
}
