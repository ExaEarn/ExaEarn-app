<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaPayOfflineReconciliationEvidence extends Model
{
    protected $table = 'exapay_offline_reconciliation_evidence';

    protected $fillable = ['claim_id', 'transfer_uuid', 'transfer_digest', 'input_output_uuid', 'parent_digest', 'canonical_payload', 'sender_signature', 'recipient_signature', 'status'];

    protected $casts = ['canonical_payload' => 'array'];

    protected $hidden = ['sender_signature', 'recipient_signature'];
}
