<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardRate extends Model
{
    protected $table = 'giftcard_rates';

    protected $fillable = [
        'brand',
        'card_type',
        'rate',
        'rate_to_usdt',
        'currency',
        'min_value',
        'max_value',
        'active',
        'source',
        'last_updated',
        'metadata',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'rate_to_usdt' => 'decimal:18',
        'active' => 'boolean',
        'last_updated' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $rate): void {
            $brand = strtolower(trim((string) ($rate->brand ?: $rate->card_type ?: '')));
            if ($brand !== '') {
                $rate->brand = $brand;
            }

            $legacyCardType = strtoupper(trim((string) ($rate->card_type ?: $rate->brand ?: '')));
            if ($legacyCardType !== '') {
                $rate->card_type = $legacyCardType;
            }

            if ($rate->rate !== null && $rate->rate_to_usdt === null) {
                $rate->rate_to_usdt = $rate->rate;
            }

            if ($rate->last_updated === null) {
                $rate->last_updated = now();
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByBrand($query, string $brand)
    {
        return $query->whereRaw('LOWER(brand) = ?', [strtolower($brand)]);
    }

    public function scopeByLegacyCardType($query, string $cardType)
    {
        return $query->whereRaw('UPPER(card_type) = ?', [strtoupper($cardType)]);
    }
}
