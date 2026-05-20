<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIRiskAlert extends Model
{
    protected $fillable = ['user_id', 'symbol', 'alert_type', 'severity', 'details', 'action_taken', 'detected_at'];

    protected $casts = [
        'details' => 'array',
        'detected_at' => 'datetime',
    ];
}
