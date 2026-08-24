<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollateralConfigurationVersion extends Model
{
    protected $fillable = [
        'collateral_configuration_id', 'version', 'before_state', 'after_state',
        'changed_by_admin_id', 'reason', 'changed_at',
    ];

    protected $casts = ['before_state' => 'array', 'after_state' => 'array', 'changed_at' => 'datetime'];
}
