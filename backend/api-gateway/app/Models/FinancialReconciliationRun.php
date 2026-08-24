<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReconciliationRun extends Model
{
    protected $fillable = ['run_id', 'status', 'differences_count', 'summary', 'started_at', 'finished_at'];

    protected $casts = ['summary' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function differences(): HasMany
    {
        return $this->hasMany(FinancialReconciliationDifference::class);
    }
}
