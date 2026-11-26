<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflinePolicyVersion extends Model
{
    public $timestamps = false;
    protected $table = 'exapay_offline_policy_versions';
    protected $fillable = ['policy_version', 'policy_digest', 'canonical_payload', 'policy_signature', 'issuer_public_key', 'issuer_key_id', 'status', 'created_by_admin_id', 'expires_at', 'created_at'];
    protected $casts = ['policy_version' => 'integer', 'expires_at' => 'datetime', 'created_at' => 'datetime'];
    protected $hidden = ['canonical_payload', 'policy_signature', 'issuer_public_key'];
}
