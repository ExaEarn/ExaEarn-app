<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiOperationalMetric extends Model
{
    protected $table = 'exaai_operational_metrics';

    protected $fillable = ['metric_key', 'metric_value', 'dimensions', 'measured_at'];

    protected $casts = [
        'metric_value' => 'decimal:8',
        'dimensions' => 'array',
        'measured_at' => 'datetime',
    ];
}
