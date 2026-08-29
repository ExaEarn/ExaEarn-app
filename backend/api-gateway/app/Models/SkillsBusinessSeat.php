<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsBusinessSeat extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'status', 'assigned_at', 'released_at'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];
}
