<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquidityReconciliationRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function differences(): HasMany
    {
        return $this->hasMany(LiquidityReconciliationDifference::class);
    }
}
