<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaPayOfflineDevice extends Model
{
    protected $table = 'exapay_offline_devices';

    protected $fillable = ['user_id', 'device_uuid', 'public_key', 'public_key_fingerprint', 'status', 'protocol_version', 'device_counter', 'journal_counter', 'last_journal_digest', 'key_version', 'key_rotated_at', 'revoked_at', 'enrolled_at', 'last_seen_at', 'suspended_at', 'suspension_reason', 'trust_class', 'attestation_status', 'attestation_format', 'attestation_digest', 'trust_epoch', 'offline_spending_allowance_minor', 'cumulative_spent_minor', 'transaction_count', 'last_counter_accepted_at'];

    protected $casts = ['protocol_version' => 'integer', 'device_counter' => 'integer', 'journal_counter' => 'integer', 'key_version' => 'integer', 'key_rotated_at' => 'datetime', 'revoked_at' => 'datetime', 'enrolled_at' => 'datetime', 'last_seen_at' => 'datetime', 'suspended_at' => 'datetime', 'trust_epoch' => 'integer', 'offline_spending_allowance_minor' => 'integer', 'cumulative_spent_minor' => 'integer', 'transaction_count' => 'integer', 'last_counter_accepted_at' => 'datetime'];

    protected $hidden = ['public_key', 'attestation_digest'];
}
