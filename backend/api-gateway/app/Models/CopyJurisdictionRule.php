<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyJurisdictionRule extends Model
{
    protected $fillable = [
        'country',
        'spot_copy_public',
        'futures_copy_public',
        'profit_share_public',
        'max_leverage',
        'terms_version',
        'status',
        'metadata',
    ];

    protected $casts = [
        'max_leverage' => 'integer',
        'metadata' => 'array',
    ];
}
