<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbCategory extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];
}
