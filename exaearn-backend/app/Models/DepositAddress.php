<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositAddress extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'address',
        'network',
        'address_type',
        'derivation_path',
        'address_index',
        'status',
        'metadata',
    ];

    protected $casts = [
        'address_index' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: find address for a given currency on a network.
     */
    public function scopeForCurrency($query, string $currency, ?string $network = null)
    {
        $query->where('currency', strtoupper($currency));

        if ($network) {
            $query->where('network', $network);
        }

        return $query;
    }
}
