<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'course_id',
        'score',
        'passed',
        'time_spent_seconds',
        'submitted_answers',
        'attempt_fingerprint',
        'submitted_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'passed' => 'boolean',
        'time_spent_seconds' => 'integer',
        'submitted_answers' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
