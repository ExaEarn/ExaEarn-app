<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Course extends Model
{
    protected $fillable = [
        'created_by',
        'category_id',
        'title',
        'slug',
        'instructor_name',
        'description',
        'thumbnail_url',
        'difficulty',
        'language',
        'duration',
        'price',
        'settlement_asset',
        'status',
        'credential_available',
        'published_at',
        'reward_amount',
        'metadata',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:8',
        'credential_available' => 'boolean',
        'published_at' => 'datetime',
        'reward_amount' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillsCategory::class, 'category_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order_index');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }
}



