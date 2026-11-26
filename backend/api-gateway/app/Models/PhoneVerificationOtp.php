<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneVerificationOtp extends Model
{
    protected $table = 'phone_verification_otps';

    protected $fillable = [
        'user_id',
        'phone',
        'country_code',
        'otp_hash',
        'otp_length',
        'sent_at',
        'expires_at',
        'verified_at',
        'attempt_count',
        'resend_count',
        'status',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
