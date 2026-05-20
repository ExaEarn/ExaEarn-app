<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycVerification extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'level',
        'document',
        'selfie',
        'document_type',
        'provider',
        'provider_id',
        'risk_score',
        'risk_flags',
        'auto_verified',
        'approved_by',
        'review_note',
    ];

    protected $casts = [
        'risk_flags' => 'array',
        'auto_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
