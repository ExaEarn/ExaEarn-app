<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiLoadRun extends Model
{
    protected $table = 'exaai_load_runs';

    protected $fillable = [
        'run_uuid',
        'scenario',
        'participants',
        'metrics',
        'status',
    ];

    protected $casts = [
        'metrics' => 'array',
    ];
}
