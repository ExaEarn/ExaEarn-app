<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAgentProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'support_enabled' => 'boolean',
        'skills' => 'array',
        'last_heartbeat_at' => 'datetime',
        'last_assigned_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
