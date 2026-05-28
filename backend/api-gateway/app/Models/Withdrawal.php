<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'transaction_id',
        'currency',
        'amount',
        'fee',
        'address',
        'network',
        'tx_hash',
        'status',
        'confirmed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'status' => TransactionStatus::class,
        'metadata' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', TransactionStatus::Pending);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', TransactionStatus::Processing);
    }
}
