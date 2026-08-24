<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingAssetNetworkConfiguration extends Model
{
    protected $guarded = [];

    protected $casts = [
        'deposit_enabled' => 'boolean',
        'withdrawal_enabled' => 'boolean',
        'memo_required' => 'boolean',
        'metadata' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ListingApplication::class, 'application_id');
    }
}

