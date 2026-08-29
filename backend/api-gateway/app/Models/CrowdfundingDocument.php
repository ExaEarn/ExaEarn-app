<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrowdfundingDocument extends Model
{
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo { return $this->belongsTo(CrowdfundingCampaign::class, 'campaign_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
}
