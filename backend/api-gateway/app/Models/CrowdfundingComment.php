<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrowdfundingComment extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_creator_reply' => 'boolean',
        'report_metadata' => 'array',
        'reported_at' => 'datetime',
        'moderated_at' => 'datetime',
    ];

    public function campaign(): BelongsTo { return $this->belongsTo(CrowdfundingCampaign::class, 'campaign_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
}
