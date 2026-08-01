<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransfer extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'from_account',
        'to_account',
        'asset',
        'amount',
        'status',
        'idempotency_key',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
