<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletBalance extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_type',
        'asset',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(InternalWalletTransaction::class, 'user_id', 'user_id')
            ->where('wallet_type', $this->wallet_type)
            ->where('asset', $this->asset);
    }
}
