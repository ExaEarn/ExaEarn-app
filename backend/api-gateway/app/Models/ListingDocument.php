<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingDocument extends Model
{
    protected $fillable = ['document_uuid', 'application_id', 'uploaded_by', 'document_type', 'original_name', 'storage_disk', 'storage_path', 'mime_type', 'size_bytes', 'visibility', 'scan_status', 'clean_at', 'scan_provider', 'scan_reference', 'checksum_sha256', 'version', 'review_status', 'reviewer_admin_id', 'reviewed_at', 'metadata'];

    protected $hidden = ['storage_disk', 'storage_path'];

    protected $casts = ['metadata' => 'array', 'reviewed_at' => 'datetime', 'clean_at' => 'datetime'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ListingApplication::class, 'application_id');
    }
}
