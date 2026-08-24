<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketMakerProgramApplication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_markets' => 'array',
        'requested_products' => 'array',
        'technical_profile' => 'array',
        'risk_profile' => 'array',
        'commercial_terms' => 'array',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(InstitutionalAccount::class, 'institution_id');
    }

    public function subaccount(): BelongsTo
    {
        return $this->belongsTo(InstitutionalSubaccount::class, 'subaccount_id');
    }
}
