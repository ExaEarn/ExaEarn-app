<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAssistantMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_assistant_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'message',
        'suggestions',
        'context_data',
        'signal_reference_id',
    ];

    protected $casts = [
        'suggestions' => 'array',
        'context_data' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIAssistantConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(TradingSignal::class, 'signal_reference_id');
    }
}
