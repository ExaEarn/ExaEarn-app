<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportChat extends Model
{
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'waiting_since' => 'datetime',
        'assigned_at' => 'datetime',
        'first_agent_response_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class, 'chat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
