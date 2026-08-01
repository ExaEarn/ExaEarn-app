<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillsInstructorEarning extends Model
{
    protected $fillable = [
        'instructor_user_id', 'course_id', 'purchase_id', 'asset', 'gross_amount',
        'platform_fee_amount', 'net_amount', 'status', 'reference', 'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:8',
        'platform_fee_amount' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(SkillsCoursePurchase::class, 'purchase_id');
    }
}
