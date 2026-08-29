<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdfundingPayout extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'completed_at' => 'datetime'];

    public function campaign(): BelongsTo { return $this->belongsTo(CrowdfundingCampaign::class, 'campaign_id'); }
    public function milestone(): BelongsTo { return $this->belongsTo(CrowdfundingMilestone::class, 'milestone_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(CrowdfundingCreator::class, 'creator_id'); }
}
