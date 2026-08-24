<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreService extends Model
{
    protected $table = 'sre_service_registry';
    protected $guarded = [];
    protected $casts = ['dependencies' => 'array', 'metadata' => 'array', 'heartbeat_at' => 'datetime', 'last_seen_at' => 'datetime'];
}
