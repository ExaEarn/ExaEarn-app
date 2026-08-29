<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportEscalation extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array'];
}
