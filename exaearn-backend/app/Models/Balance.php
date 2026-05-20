<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    protected $fillable = [
        'user_id', 
        'asset', 
        'funding_available', 
        'spot_available', 
        'spot_locked', 
        'futures_available', 
        'futures_margin'
    ];
}
