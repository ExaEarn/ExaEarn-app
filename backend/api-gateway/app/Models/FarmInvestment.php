<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmInvestment extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'shares_owned',
        'investment_amount',
        'status',
        'ownership_reference',
        'locked_until',
        'metadata',
        'idempotency_key',
        'reservation_id',
        'ledger_transaction_id',
        'pricing_decision_id',
        'asset',
        'financial_status',
        'compliance_snapshot',
        'settled_at',
        'cancelled_at',
    ];

    protected $casts = [
        'investment_amount' => 'decimal:8',
        'locked_until' => 'datetime',
        'metadata' => 'array',
        'compliance_snapshot' => 'array',
        'settled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(FarmingProject::class, 'project_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(FarmLease::class, 'investment_id');
    }
}
