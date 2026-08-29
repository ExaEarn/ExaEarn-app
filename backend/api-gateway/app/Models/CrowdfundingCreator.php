<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrowdfundingCreator extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function campaigns(): HasMany { return $this->hasMany(CrowdfundingCampaign::class, 'creator_id'); }
}
