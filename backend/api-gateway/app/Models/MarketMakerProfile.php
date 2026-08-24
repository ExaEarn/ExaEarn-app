<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketMakerProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'approved_markets' => 'array',
        'limits' => 'array',
        'risk_profile' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(InstitutionalAccount::class, 'institution_id');
    }

    public function subaccount(): BelongsTo
    {
        return $this->belongsTo(InstitutionalSubaccount::class, 'subaccount_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MarketMakerMarketAssignment::class, 'market_maker_id');
    }
}
