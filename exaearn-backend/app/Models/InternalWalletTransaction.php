<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalWalletTransaction extends Model
{
    protected $table = 'internal_wallet_transactions';

    protected $fillable = [
        'user_id',
        'type',
        'wallet_type',
        'asset',
        'amount',
        'reference',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
