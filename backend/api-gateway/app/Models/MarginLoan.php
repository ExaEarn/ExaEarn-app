<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarginLoan extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_PARTIALLY_REPAID = 'PARTIALLY_REPAID';
    public const STATUS_REPAID = 'REPAID';
    public const STATUS_LIQUIDATING = 'LIQUIDATING';
    public const STATUS_DEFAULTED = 'DEFAULTED';

    protected $fillable = [
        'loan_uuid',
        'margin_account_id',
        'user_id',
        'asset',
        'principal',
        'accrued_interest',
        'interest_rate',
        'opened_at',
        'last_accrual_at',
        'status',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'principal' => 'decimal:18',
        'accrued_interest' => 'decimal:18',
        'interest_rate' => 'decimal:8',
        'opened_at' => 'datetime',
        'last_accrual_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function marginAccount(): BelongsTo
    {
        return $this->belongsTo(MarginAccount::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(MarginInterestAccrual::class);
    }
}
