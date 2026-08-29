<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgriReconciliationFinding extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expected' => 'array',
        'actual' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];
}
