<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardRate extends Model
{
    protected $table = 'giftcard_rates';

    protected $fillable = [
        'brand',
        'rate',
        'currency',
        'min_value',
        'max_value',
        'active',
        'metadata',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByBrand($query, string $brand)
    {
        return $query->where('brand', $brand);
    }
}
