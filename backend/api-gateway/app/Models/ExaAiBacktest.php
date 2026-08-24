<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiBacktest extends Model
{
    protected $table = 'exaai_backtests';

    protected $fillable = [
        'backtest_uuid',
        'strategy_definition_id',
        'strategy_version_id',
        'dataset_reference',
        'period_start',
        'period_end',
        'parameters',
        'assumptions',
        'results',
        'status',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'parameters' => 'array',
        'assumptions' => 'array',
        'results' => 'array',
    ];
}
