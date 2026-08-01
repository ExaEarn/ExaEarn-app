<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillsCredential extends Model
{
    protected $fillable = ['user_id', 'course_id', 'credential_code', 'title', 'skills', 'status', 'issued_at', 'verification_hash', 'metadata'];

    protected $casts = [
        'skills' => 'array',
        'issued_at' => 'datetime',
        'metadata' => 'array',
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
