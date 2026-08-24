<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BestExecutionAudit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_quantity' => 'decimal:18',
        'market_state' => 'array',
        'sources_considered' => 'array',
        'route_selected' => 'array',
        'result' => 'array',
        'quality_score' => 'decimal:8',
    ];
}
