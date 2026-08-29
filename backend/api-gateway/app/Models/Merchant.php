<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $fillable = [
        'merchant_id',
        'user_id',
        'business_name',
        'organization_name',
        'country',
        'business_type',
        'kyb_status',
        'settlement_currency',
        'settlement_account_reference',
        'pricing_profile',
        'environment',
        'status',
        'risk_status',
        'profile',
        'metadata',
        'activated_at',
    ];

    protected $casts = [
        'profile' => 'array',
        'metadata' => 'array',
        'activated_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(MerchantTeamMember::class);
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(MerchantPaymentLink::class);
    }
}
