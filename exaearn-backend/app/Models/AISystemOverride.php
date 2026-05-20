<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AISystemOverride extends Model
{
    protected $fillable = ['symbol', 'enabled', 'params', 'set_by', 'expires_at'];

    protected $casts = [
        'enabled' => 'boolean',
        'params' => 'array',
        'expires_at' => 'datetime',
    ];
}
