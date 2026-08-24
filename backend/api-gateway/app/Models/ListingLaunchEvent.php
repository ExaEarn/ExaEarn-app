<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingLaunchEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
        'executed_at' => 'datetime',
        'result' => 'array',
    ];
}

