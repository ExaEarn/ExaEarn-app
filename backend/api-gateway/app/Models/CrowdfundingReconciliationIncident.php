<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdfundingReconciliationIncident extends Model
{
    protected $guarded = [];
    protected $casts = ['evidence' => 'array', 'resolved_at' => 'datetime'];

    public function campaign(): BelongsTo { return $this->belongsTo(CrowdfundingCampaign::class, 'campaign_id'); }
}
