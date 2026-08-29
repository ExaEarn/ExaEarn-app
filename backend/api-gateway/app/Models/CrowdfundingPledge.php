<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdfundingPledge extends Model
{
    protected $guarded = [];
    protected $casts = ['pricing_snapshot' => 'array', 'metadata' => 'array', 'anonymous_display' => 'boolean', 'settled_at' => 'datetime', 'refunded_at' => 'datetime'];

    public function campaign(): BelongsTo { return $this->belongsTo(CrowdfundingCampaign::class, 'campaign_id'); }
    public function backer(): BelongsTo { return $this->belongsTo(User::class, 'backer_id'); }
}
