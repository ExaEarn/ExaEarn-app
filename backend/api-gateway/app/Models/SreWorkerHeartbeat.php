<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreWorkerHeartbeat extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'started_at' => 'datetime', 'last_heartbeat_at' => 'datetime', 'last_job_at' => 'datetime'];
}
