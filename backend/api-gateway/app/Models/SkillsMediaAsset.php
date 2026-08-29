<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillsMediaAsset extends Model
{
    protected $fillable = ['owner_user_id', 'course_id', 'lesson_id', 'asset_type', 'visibility', 'provider', 'disk', 'storage_reference', 'safe_filename', 'mime_type', 'size_bytes', 'processing_state', 'metadata', 'uploaded_at'];

    protected $casts = ['metadata' => 'array', 'uploaded_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
