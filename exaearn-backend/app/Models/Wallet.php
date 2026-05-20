<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'available_balance',
        'locked_balance',
    ];

    protected $casts = [
        'available_balance' => 'decimal:8',
        'locked_balance' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function depositAddresses(): HasMany
    {
        return $this->hasMany(DepositAddress::class, 'user_id', 'user_id')
            ->where('currency', $this->currency);
    }

    /**
     * Computed pending balance: sum of pending deposit amounts for this wallet.
     */
    public function getPendingBalanceAttribute(): string
    {
        return (string) Transaction::where('user_id', $this->user_id)
            ->where('currency', $this->currency)
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->sum('amount');
    }

    /**
     * Total balance = available + locked.
     */
    public function getTotalBalanceAttribute(): string
    {
        if (function_exists('bcadd')) {
            return bcadd((string) $this->available_balance, (string) $this->locked_balance, 8);
        }

        return number_format((float) $this->available_balance + (float) $this->locked_balance, 8, '.', '');
    }
}
