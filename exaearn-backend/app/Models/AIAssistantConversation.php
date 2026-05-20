<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIAssistantConversation extends Model
{
    use HasFactory;

    protected $table = 'ai_assistant_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'context',
        'market_condition',
        'is_active',
    ];

    protected $casts = [
        'context' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AIAssistantMessage::class, 'conversation_id');
    }
}
