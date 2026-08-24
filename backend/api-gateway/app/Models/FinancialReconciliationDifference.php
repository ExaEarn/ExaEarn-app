<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialReconciliationDifference extends Model
{
    protected $fillable = ['financial_reconciliation_run_id', 'scope', 'severity', 'code', 'message', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
