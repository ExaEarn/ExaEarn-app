<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiSurveillanceCase extends Model
{
    protected $table = 'exaai_surveillance_cases';

    protected $fillable = [
        'case_uuid',
        'user_id',
        'signal_type',
        'severity',
        'status',
        'evidence',
        'reviewed_at',
        'reviewed_by',
        'resolution',
    ];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
    ];
}
