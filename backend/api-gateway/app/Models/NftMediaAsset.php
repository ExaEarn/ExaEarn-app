<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NftMediaAsset extends Model
{
    protected $fillable = [
        'owner_user_id', 'nft_id', 'collection_id', 'media_type', 'visibility', 'storage_provider',
        'storage_key', 'original_filename', 'safe_filename', 'mime_type', 'size_bytes', 'checksum',
        'content_hash', 'status', 'processing_status', 'public_uri', 'metadata_uri', 'metadata',
        'metadata_hash', 'metadata_version', 'immutable_finalized_at', 'created_by_user_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'immutable_finalized_at' => 'datetime',
    ];

    public function nft(): BelongsTo
    {
        return $this->belongsTo(Nft::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(NftCollection::class);
    }
}
