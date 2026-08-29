<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateProfile extends Model
{
    protected $fillable = [
        'user_id',
        'affiliate_tier_id',
        'status',
        'payout_asset',
        'metadata',
        'approved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'approved_at' => 'datetime',
    ];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(AffiliateTier::class, 'affiliate_tier_id');
    }
}
