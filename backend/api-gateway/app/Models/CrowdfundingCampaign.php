<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrowdfundingCampaign extends Model
{
    protected $guarded = [];
    protected $hidden = ['metadata'];
    protected $casts = [
        'pricing_snapshot' => 'array',
        'metadata' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(CrowdfundingCreator::class, 'creator_id'); }
    public function pledges(): HasMany { return $this->hasMany(CrowdfundingPledge::class, 'campaign_id'); }
    public function milestones(): HasMany { return $this->hasMany(CrowdfundingMilestone::class, 'campaign_id'); }
    public function updates(): HasMany { return $this->hasMany(CrowdfundingUpdate::class, 'campaign_id'); }
    public function payouts(): HasMany { return $this->hasMany(CrowdfundingPayout::class, 'campaign_id'); }
    public function comments(): HasMany { return $this->hasMany(CrowdfundingComment::class, 'campaign_id'); }
    public function documents(): HasMany { return $this->hasMany(CrowdfundingDocument::class, 'campaign_id'); }
}
