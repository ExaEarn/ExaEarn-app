<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaAiReconciliationRun extends Model
{
    protected $table = 'exaai_reconciliation_runs';

    protected $fillable = [
        'run_uuid',
        'status',
        'portfolios_checked',
        'decisions_checked',
        'differences_found',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function differences(): HasMany
    {
        return $this->hasMany(ExaAiReconciliationDifference::class, 'run_id');
    }
}
