<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityCase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
    ];
}
