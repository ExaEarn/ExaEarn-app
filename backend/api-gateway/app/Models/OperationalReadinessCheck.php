<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalReadinessCheck extends Model
{
    protected $fillable = ['check_id', 'overall_status', 'components', 'blockers', 'checked_at'];

    protected $casts = ['components' => 'array', 'blockers' => 'array', 'checked_at' => 'datetime'];
}
