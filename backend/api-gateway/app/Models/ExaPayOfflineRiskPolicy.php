<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaPayOfflineRiskPolicy extends Model
{
    protected $table = 'exapay_offline_risk_policies';

    protected $fillable = ['enabled', 'max_amount_minor', 'max_outstanding_per_user_minor', 'max_devices_per_user', 'instrument_lifetime_seconds', 'max_hops', 'max_transfers_per_journal', 'max_cumulative_spending_minor', 'minimum_protocol_version', 'policy_version', 'policy_digest', 'policy_signature', 'policy_public_key', 'policy_expires_at', 'updated_by_admin_id', 'max_device_exposure_minor', 'max_incoming_exposure_per_user_minor', 'max_transactions_per_device', 'max_journal_bytes', 'supported_assets', 'allowed_device_classes', 'trust_epoch', 'require_enrolled_device'];

    protected $casts = ['enabled' => 'boolean', 'max_amount_minor' => 'integer', 'max_outstanding_per_user_minor' => 'integer', 'max_devices_per_user' => 'integer', 'instrument_lifetime_seconds' => 'integer', 'max_hops' => 'integer', 'max_transfers_per_journal' => 'integer', 'max_cumulative_spending_minor' => 'integer', 'minimum_protocol_version' => 'integer', 'policy_version' => 'integer', 'policy_expires_at' => 'datetime', 'max_device_exposure_minor' => 'integer', 'max_incoming_exposure_per_user_minor' => 'integer', 'max_transactions_per_device' => 'integer', 'max_journal_bytes' => 'integer', 'supported_assets' => 'array', 'allowed_device_classes' => 'array', 'trust_epoch' => 'integer', 'require_enrolled_device' => 'boolean'];

    protected $hidden = ['policy_signature', 'policy_public_key'];
}
