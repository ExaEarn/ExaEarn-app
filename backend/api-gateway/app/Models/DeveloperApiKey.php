<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeveloperApiKey extends Model
{
    protected $fillable = [
        'key_uuid',
        'user_id',
        'project_id',
        'created_by',
        'institution_id',
        'subaccount_id',
        'name',
        'environment',
        'rate_profile',
        'key_prefix',
        'key_hash',
        'encrypted_api_key',
        'encrypted_secret',
        'secret_hash',
        'passphrase_hash',
        'status',
        'last_used_at',
        'expires_at',
        'disabled_at',
        'revoked_at',
        'revoked_by',
        'metadata',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'disabled_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'key_hash',
        'encrypted_api_key',
        'encrypted_secret',
        'secret_hash',
        'passphrase_hash',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DeveloperProject::class, 'project_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(DeveloperApiKeyPermission::class, 'api_key_id');
    }

    public function ipWhitelists(): HasMany
    {
        return $this->hasMany(DeveloperApiKeyIpWhitelist::class, 'api_key_id');
    }
}
