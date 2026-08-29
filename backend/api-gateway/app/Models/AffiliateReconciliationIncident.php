<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateReconciliationIncident extends Model
{
    protected $fillable = [
        'incident_uuid',
        'type',
        'severity',
        'status',
        'affiliate_user_id',
        'evidence',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'evidence' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];
}
