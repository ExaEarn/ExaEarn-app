<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportChatMessage extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'delivered_at' => 'datetime', 'read_at' => 'datetime'];
}
