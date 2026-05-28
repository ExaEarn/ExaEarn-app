<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryAccount extends Model
{
    protected $fillable = [
        'provider',
        'currency',
        'available_balance',
        'locked_balance',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'available_balance' => 'decimal:18',
        'locked_balance' => 'decimal:18',
        'last_synced_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function getTotalBalanceAttribute(): string
    {
        return bcadd((string) $this->available_balance, (string) $this->locked_balance, 18);
    }
}