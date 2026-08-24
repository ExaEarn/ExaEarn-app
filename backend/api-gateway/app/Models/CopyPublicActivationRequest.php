<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyPublicActivationRequest extends Model
{
    protected $fillable = [
        'request_id',
        'requested_mode',
        'status',
        'requested_by',
        'approved_by',
        'reason',
        'readiness_snapshot',
        'approved_at',
    ];

    protected $casts = [
        'readiness_snapshot' => 'array',
        'approved_at' => 'datetime',
    ];
}
