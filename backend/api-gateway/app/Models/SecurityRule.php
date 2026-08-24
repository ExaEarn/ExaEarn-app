<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'effective_at' => 'datetime',
    ];
}
