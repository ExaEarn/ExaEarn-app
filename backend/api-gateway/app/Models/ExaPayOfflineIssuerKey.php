<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineIssuerKey extends Model
{
    protected $table = 'exapay_offline_issuer_keys';
    protected $fillable = ['key_id', 'public_key', 'fingerprint', 'status', 'activated_at', 'compromised_at', 'compromise_reason', 'recorded_by_admin_id'];
    protected $casts = ['activated_at' => 'datetime', 'compromised_at' => 'datetime'];
    protected $hidden = ['public_key'];
}
