<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LedgerTransaction extends Model
{
    protected $fillable = [
        'reference',
        'description',
        'transaction_type',
        'source_service',
        'initiated_by_type',
        'initiated_by_id',
        'reversal_of_transaction_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'reference', 'reference');
    }

    public function financeEvent(): HasOne
    {
        return $this->hasOne(FinanceFinancialEvent::class, 'ledger_transaction_id');
    }
}
