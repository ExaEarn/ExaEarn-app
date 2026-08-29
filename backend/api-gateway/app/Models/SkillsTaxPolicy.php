<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsTaxPolicy extends Model
{
    protected $fillable = ['country', 'entity_type', 'income_category', 'payout_asset', 'outcome', 'withholding_rate', 'policy_version', 'status', 'effective_from', 'effective_to', 'metadata'];

    protected $casts = [
        'withholding_rate' => 'decimal:8',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'metadata' => 'array',
    ];
}
