<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdfundingUpdate extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'published_at' => 'datetime'];

    public function campaign(): BelongsTo { return $this->belongsTo(CrowdfundingCampaign::class, 'campaign_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(CrowdfundingCreator::class, 'creator_id'); }
}
