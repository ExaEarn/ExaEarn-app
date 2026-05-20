<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginDevice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'device_name',
        'user_agent',
        'ip_address',
        'fingerprint_hash',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
