<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreHealthSnapshot extends Model
{
    protected $guarded = [];
    protected $casts = ['liveness' => 'array', 'readiness' => 'array', 'dependency_health' => 'array', 'business_readiness' => 'array', 'reason_codes' => 'array', 'impact' => 'array', 'captured_at' => 'datetime'];
}
