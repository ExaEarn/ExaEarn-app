<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightGameResponsibleGamingProfile extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'requested_at',
        'effective_at',
        'expires_at',
        'reason_category',
        'policy_version',
        'limits',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'effective_at' => 'datetime',
        'expires_at' => 'datetime',
        'limits' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
