<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipTierDefinition extends Model
{
    protected $fillable = ['tier', 'min_30d_spot_volume', 'min_30d_futures_volume', 'min_average_balance', 'benefits', 'active'];

    protected $casts = ['benefits' => 'array', 'active' => 'boolean'];
}
