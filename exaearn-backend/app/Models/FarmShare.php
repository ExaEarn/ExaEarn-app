<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmShare extends Model
{
    protected $fillable = [
        'project_id',
        'total_shares',
        'price_per_share',
        'shares_available',
        'ownership_model',
        'token_contract_address',
        'token_symbol',
        'metadata',
    ];

    protected $casts = [
        'price_per_share' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FarmingProject::class, 'project_id');
    }
}
