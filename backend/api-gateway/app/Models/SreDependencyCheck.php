<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreDependencyCheck extends Model
{
    protected $guarded = [];
    protected $casts = ['evidence' => 'array', 'checked_at' => 'datetime'];
}
