<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaPayOfflineRedemption extends Model
{
    protected $table = 'exapay_offline_redemptions';

    protected $fillable = ['redemption_uuid', 'claim_id', 'output_uuid', 'user_id', 'asset', 'amount_minor', 'idempotency_key', 'request_digest', 'ledger_reference', 'ledger_transaction_id', 'status', 'redeemed_at'];

    protected $casts = ['amount_minor' => 'integer', 'redeemed_at' => 'datetime'];

    protected $hidden = ['idempotency_key', 'request_digest'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExaPayOfflineReconciliationClaim::class, 'claim_id');
    }
}
