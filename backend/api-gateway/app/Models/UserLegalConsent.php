<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLegalConsent extends Model
{
    protected $fillable = ['user_id', 'consent_type', 'document_version', 'accepted_at', 'ip_address_hash', 'user_agent_hash', 'context'];

    protected $casts = ['accepted_at' => 'datetime', 'context' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
