<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaPayOfflineReconciliationClaim extends Model
{
    protected $table = 'exapay_offline_reconciliation_claims';

    protected $fillable = ['claim_uuid', 'user_id', 'idempotency_key', 'request_digest', 'expected_output_uuid', 'expected_amount_minor', 'evidence_revision', 'evaluated_evidence_revision', 'status', 'reason_code', 'result', 'verified_at', 'redeemed_at'];

    protected $casts = ['expected_amount_minor' => 'integer', 'evidence_revision' => 'integer', 'evaluated_evidence_revision' => 'integer', 'result' => 'array', 'verified_at' => 'datetime', 'redeemed_at' => 'datetime'];

    protected $hidden = ['idempotency_key', 'request_digest'];

    public function evidence(): HasMany
    {
        return $this->hasMany(ExaPayOfflineReconciliationEvidence::class, 'claim_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExaPayOfflineReconciliationEvent::class, 'claim_id');
    }
}
