<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingReviewAssignment extends Model
{
    protected $fillable = [
        'assignment_uuid',
        'application_id',
        'review_domain',
        'reviewer_admin_id',
        'assigned_by_admin_id',
        'assigned_at',
        'status',
        'reassignment_reason',
    ];

    protected $casts = ['assigned_at' => 'datetime'];
}
