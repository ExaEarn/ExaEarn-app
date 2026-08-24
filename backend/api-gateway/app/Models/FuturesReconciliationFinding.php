<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuturesReconciliationFinding extends Model
{
    protected $fillable = [
        'finding_id',
        'scope',
        'symbol',
        'severity',
        'status',
        'message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
