<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingReviewDecisionEvent extends Model
{
    protected $fillable = [
        'event_uuid',
        'application_id',
        'review_id',
        'review_domain',
        'reviewer_admin_id',
        'decision',
        'reason',
        'evidence',
        'application_revision',
        'configuration_fingerprint',
        'decided_at',
    ];

    protected $casts = ['evidence' => 'array', 'decided_at' => 'datetime'];
}
