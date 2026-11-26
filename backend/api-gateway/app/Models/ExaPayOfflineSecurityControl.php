<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineSecurityControl extends Model
{
    protected $table = 'exapay_offline_security_controls';
    protected $fillable = ['issuance_suspended', 'redemption_suspended', 'trust_epoch', 'blocked_device_families', 'suspension_reason', 'updated_by_admin_id'];
    protected $casts = ['issuance_suspended' => 'boolean', 'redemption_suspended' => 'boolean', 'trust_epoch' => 'integer', 'blocked_device_families' => 'array'];
}
