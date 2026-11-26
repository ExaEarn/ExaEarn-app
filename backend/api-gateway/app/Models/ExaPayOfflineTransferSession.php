<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineTransferSession extends Model
{
    protected $table = 'exapay_offline_transfer_sessions';

    protected $fillable = ['transfer_uuid', 'claimed_transfer_uuid', 'transfer_digest', 'sender_device_id', 'recipient_device_id', 'sender_key_version', 'recipient_key_version', 'input_output_uuid', 'recipient_output_uuid', 'change_output_uuid', 'idempotency_key', 'input_amount_minor', 'transfer_amount_minor', 'change_amount_minor', 'transfer_counter', 'protocol_version', 'hop_count', 'nonce_digest', 'parent_digest', 'canonical_payload', 'sender_signature', 'recipient_signature', 'status', 'prepared_at', 'committed_at', 'acknowledged_at'];

    protected $casts = ['canonical_payload' => 'array', 'input_amount_minor' => 'integer', 'transfer_amount_minor' => 'integer', 'change_amount_minor' => 'integer', 'transfer_counter' => 'integer', 'protocol_version' => 'integer', 'hop_count' => 'integer', 'prepared_at' => 'datetime', 'committed_at' => 'datetime', 'acknowledged_at' => 'datetime'];

    protected $hidden = ['sender_signature', 'recipient_signature'];
}
