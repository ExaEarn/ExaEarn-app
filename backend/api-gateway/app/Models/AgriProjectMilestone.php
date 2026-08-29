<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgriProjectMilestone extends Model
{
    protected $guarded = [];

    protected $casts = [
        'release_amount' => 'decimal:18',
        'target_at' => 'datetime',
        'approved_at' => 'datetime',
        'evidence_required' => 'boolean',
        'metadata' => 'array',
    ];
}
