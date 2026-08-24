<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'project_id', 'api_key_id', 'event_type', 'severity', 'message', 'context', 'created_at'];

    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];
}
