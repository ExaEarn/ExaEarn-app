<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineDeviceKeyHistory extends Model
{
    protected $table = 'exapay_offline_device_key_history';
    protected $fillable = ['device_id', 'key_version', 'public_key', 'public_key_fingerprint', 'status', 'valid_from', 'revoked_at'];
    protected $casts = ['key_version' => 'integer', 'valid_from' => 'datetime', 'revoked_at' => 'datetime'];
    protected $hidden = ['public_key'];
}
