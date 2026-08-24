<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreQueueState extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'checked_at' => 'datetime'];
}
