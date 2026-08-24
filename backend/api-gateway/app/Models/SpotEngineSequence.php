<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotEngineSequence extends Model
{
    protected $fillable = [
        'market_id',
        'market_symbol',
        'last_sequence',
    ];
}
