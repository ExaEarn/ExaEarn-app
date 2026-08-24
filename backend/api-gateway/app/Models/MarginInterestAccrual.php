<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarginInterestAccrual extends Model
{
    protected $fillable = [
        'accrual_id',
        'margin_loan_id',
        'asset',
        'principal_basis',
        'interest_rate',
        'interest_amount',
        'period_start',
        'period_end',
        'metadata',
    ];

    protected $casts = [
        'principal_basis' => 'decimal:18',
        'interest_rate' => 'decimal:8',
        'interest_amount' => 'decimal:18',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'metadata' => 'array',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(MarginLoan::class, 'margin_loan_id');
    }
}
