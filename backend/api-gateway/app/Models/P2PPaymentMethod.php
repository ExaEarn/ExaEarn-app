<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2PPaymentMethod extends Model
{
    protected $table = 'p2p_payment_methods';

    protected $fillable = [
        'user_id',
        'method_type',
        'display_name',
        'fiat_currency',
        'bank_name',
        'bank_code',
        'account_name',
        'account_number',
        'payment_note',
        'is_enabled',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
