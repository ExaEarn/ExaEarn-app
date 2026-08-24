<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingReview extends Model
{
    protected $fillable = ['review_uuid', 'application_id', 'reviewer_admin_id', 'review_type', 'status', 'score', 'scorecard', 'risk_flags', 'notes', 'completed_at'];

    protected $casts = ['scorecard' => 'array', 'risk_flags' => 'array', 'completed_at' => 'datetime'];
}
