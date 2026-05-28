<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2PMessage extends Model
{
    protected $table = 'p2p_messages';

    protected $fillable = [
        'trade_id',
        'sender_id',
        'encrypted_message',
        'attachment',
        'moderation_status',
        'moderation_flags',
    ];

    protected $casts = [
        'moderation_flags' => 'array',
    ];

    protected $appends = [
        'message',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(P2PTrade::class, 'trade_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getMessageAttribute(): ?string
    {
        if (!$this->encrypted_message) {
            return null;
        }

        return decrypt($this->encrypted_message);
    }
}
