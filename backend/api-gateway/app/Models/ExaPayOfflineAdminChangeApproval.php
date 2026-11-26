<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineAdminChangeApproval extends Model
{
    protected $table = 'exapay_offline_admin_change_approvals';
    protected $fillable = ['approval_uuid', 'change_type', 'proposed_change', 'change_digest', 'idempotency_key', 'requested_by_admin_id', 'approved_by_admin_id', 'status', 'expires_at', 'approved_at'];
    protected $casts = ['proposed_change' => 'array', 'expires_at' => 'datetime', 'approved_at' => 'datetime'];
    protected $hidden = ['proposed_change'];
}
