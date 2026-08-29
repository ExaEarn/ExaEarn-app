<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'content',
        'video_url',
        'duration_seconds',
        'minimum_watch_seconds',
        'order_index',
        'metadata',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'minimum_watch_seconds' => 'integer',
        'order_index' => 'integer',
        'metadata' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(SkillsMediaAsset::class);
    }
}
