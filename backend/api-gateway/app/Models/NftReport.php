<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class NftReport extends Model
{
    protected $fillable = ['nft_id', 'listing_id', 'reported_by_user_id', 'report_type', 'status', 'reason', 'evidence'];
    protected $casts = ['evidence' => 'array'];

    public function nft(): BelongsTo
    {
        return $this->belongsTo(Nft::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(NftListing::class);
    }
}
