<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperApiRequestLog extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'project_id',
        'api_key_id',
        'environment',
        'method',
        'path',
        'status_code',
        'latency_ms',
        'ip_address',
        'error_code',
        'metadata',
    ];

    protected $casts = ['metadata' => 'array'];
}
