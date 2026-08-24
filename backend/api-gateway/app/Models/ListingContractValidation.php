<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingContractValidation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'submitted_metadata' => 'array',
        'validated_metadata' => 'array',
        'risk_flags' => 'array',
        'checked_at' => 'datetime',
    ];
}

