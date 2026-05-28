<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardInventory extends Model
{
    protected $table = 'giftcard_inventory';

    protected $fillable = [
        'brand',
        'card_value',
        'currency',
        'encrypted_card_code',
        'encrypted_card_pin',
        'submission_id',
        'available',
        'sold_at',
        'sold_to_user_id',
        'metadata',
    ];

    protected $casts = [
        'card_value' => 'decimal:2',
        'available' => 'boolean',
        'sold_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GiftCardSubmission::class);
    }

    public function soldToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_to_user_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }

    public function scopeByBrand($query, string $brand)
    {
        return $query->where('brand', $brand);
    }
}