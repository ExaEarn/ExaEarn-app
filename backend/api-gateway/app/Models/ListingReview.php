<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingReview extends Model
{
    protected $fillable = ['review_uuid', 'application_id', 'reviewer_admin_id', 'assigned_by_admin_id', 'assigned_at', 'assignment_reason', 'review_type', 'status', 'application_revision', 'configuration_fingerprint', 'score', 'scorecard', 'risk_flags', 'notes', 'completed_at'];

    protected $casts = ['scorecard' => 'array', 'risk_flags' => 'array', 'assigned_at' => 'datetime', 'completed_at' => 'datetime'];
}
