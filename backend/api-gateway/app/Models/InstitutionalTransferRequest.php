<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalTransferRequest extends Model
{
    protected $fillable = [
        'transfer_uuid', 'institution_id', 'source_subaccount_id', 'destination_subaccount_id',
        'initiated_by_user_id', 'approved_by_user_id', 'asset', 'amount', 'status', 'idempotency_key',
        'approval_policy', 'approval_threshold', 'ledger_reference', 'reference_note', 'approved_at',
        'completed_at', 'metadata',
    ];

    protected $casts = ['approved_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(InstitutionalAccount::class, 'institution_id');
    }
}
