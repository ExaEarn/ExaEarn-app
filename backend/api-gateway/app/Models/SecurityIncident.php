<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityIncident extends Model
{
    protected $guarded = [];

    protected $casts = [
        'timeline' => 'array',
        'impact' => 'array',
        'corrective_actions' => 'array',
        'resolved_at' => 'datetime',
    ];
}
