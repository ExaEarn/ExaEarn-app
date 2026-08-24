<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreRecoveryAction extends Model
{
    protected $guarded = [];
    protected $casts = ['prechecks' => 'array', 'result' => 'array', 'approved_at' => 'datetime', 'executed_at' => 'datetime'];
}
