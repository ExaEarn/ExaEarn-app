<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudLog extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'risk_score',
        'risk_level',
        'reason',
        'ip',
        'device',
        'payload',
    ];

    protected $casts = [
        'reason' => 'array',
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GiftcardOrder::class, 'order_id');
    }
}
