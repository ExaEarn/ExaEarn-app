<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiatTreasuryTransaction extends Model
{
    protected $table = 'treasury_transactions';

    protected $fillable = [
        'provider',
        'type',
        'amount',
        'currency',
        'reference',
        'status',
        'meta_data',
    ];

    protected $casts = [
        'amount' => 'decimal:18',
        'meta_data' => 'array',
    ];

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByReference($query, string $reference)
    {
        return $query->where('reference', $reference);
    }
}