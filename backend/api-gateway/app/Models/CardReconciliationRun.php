<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardReconciliationRun extends Model
{
    protected $fillable = [
        'run_uuid',
        'status',
        'summary',
        'findings',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'findings' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
