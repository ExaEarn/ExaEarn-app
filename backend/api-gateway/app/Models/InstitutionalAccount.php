<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionalAccount extends Model
{
    protected $fillable = [
        'institution_uuid', 'application_id', 'master_user_id', 'legal_name', 'trading_name',
        'registration_number', 'country_of_incorporation', 'business_type', 'status', 'kyb_status',
        'compliance_status', 'risk_rating', 'vip_tier', 'fee_profile_id', 'account_manager_id',
        'capability_flags', 'approved_at', 'activated_at', 'metadata',
    ];

    protected $casts = ['capability_flags' => 'array', 'approved_at' => 'datetime', 'activated_at' => 'datetime', 'metadata' => 'array'];

    public function masterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_user_id');
    }

    public function subaccounts(): HasMany
    {
        return $this->hasMany(InstitutionalSubaccount::class, 'institution_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(InstitutionalMembership::class, 'institution_id');
    }

    public function feeProfile(): BelongsTo
    {
        return $this->belongsTo(InstitutionalFeeProfile::class, 'fee_profile_id');
    }
}
