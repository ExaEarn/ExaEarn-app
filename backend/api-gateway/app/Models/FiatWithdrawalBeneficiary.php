<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiatWithdrawalBeneficiary extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'currency',
        'provider',
        'bank_code',
        'bank_name',
        'account_number',
        'account_name',
        'masked_account_number',
        'is_default',
        'status',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'account_number',
        'bank_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
