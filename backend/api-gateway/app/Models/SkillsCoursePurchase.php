<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillsCoursePurchase extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'asset', 'gross_amount', 'platform_fee_amount', 'instructor_amount',
        'commission_rate', 'status', 'reference', 'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:8',
        'platform_fee_amount' => 'decimal:8',
        'instructor_amount' => 'decimal:8',
        'commission_rate' => 'decimal:6',
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
