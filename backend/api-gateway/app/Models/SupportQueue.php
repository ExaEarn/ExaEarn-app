<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportQueue extends Model
{
    protected $guarded = [];
    protected $casts = ['products' => 'array', 'categories' => 'array', 'active' => 'boolean'];
}
