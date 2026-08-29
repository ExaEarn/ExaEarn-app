<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSlaPolicy extends Model
{
    protected $guarded = [];
    protected $casts = ['pause_waiting_for_user' => 'boolean', 'active' => 'boolean'];
}
