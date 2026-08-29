<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdfundingRefundBatch extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array'];

    public function campaign(): BelongsTo { return $this->belongsTo(CrowdfundingCampaign::class, 'campaign_id'); }
}
