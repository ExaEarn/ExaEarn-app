<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantApiKey extends Model
{
    protected $fillable = [
        'key_id',
        'merchant_id',
        'developer_api_key_id',
        'environment',
        'name',
        'key_prefix',
        'key_hash',
        'scopes',
        'ip_allowlist',
        'status',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'ip_allowlist' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
