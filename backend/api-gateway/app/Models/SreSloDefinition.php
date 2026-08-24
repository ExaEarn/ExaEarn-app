<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SreSloDefinition extends Model
{
    protected $guarded = [];

    protected $casts = ['metadata' => 'array'];
}

