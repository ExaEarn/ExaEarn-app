<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiReconciliationDifference extends Model
{
    protected $table = 'exaai_reconciliation_differences';

    protected $fillable = [
        'run_id',
        'user_id',
        'difference_type',
        'severity',
        'evidence',
    ];

    protected $casts = [
        'evidence' => 'array',
    ];
}
