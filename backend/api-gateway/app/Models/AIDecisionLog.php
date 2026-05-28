<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIDecisionLog extends Model
{
    protected $fillable = ['symbol', 'decision_type', 'inputs', 'outputs', 'safety_applied', 'manual_override', 'decided_at'];

    protected $casts = [
        'inputs' => 'array',
        'outputs' => 'array',
        'safety_applied' => 'array',
        'manual_override' => 'boolean',
        'decided_at' => 'datetime',
    ];
}
