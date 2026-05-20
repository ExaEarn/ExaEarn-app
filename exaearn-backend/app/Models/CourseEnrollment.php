<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'progress_percentage',
        'completed',
        'completed_at',
        'watch_seconds',
        'last_unlocked_lesson_order',
        'progress_metadata',
    ];

    protected $casts = [
        'progress_percentage' => 'decimal:2',
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'watch_seconds' => 'integer',
        'last_unlocked_lesson_order' => 'integer',
        'progress_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
