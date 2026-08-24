<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryRebalancingRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'actions' => 'array',
        'metadata' => 'array',
        'evaluated_at' => 'datetime',
    ];
}
