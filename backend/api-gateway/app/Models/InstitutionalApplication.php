<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalApplication extends Model
{
    protected $fillable = [
        'application_uuid', 'user_id', 'legal_company_name', 'trading_name', 'incorporation_country',
        'registration_number', 'business_type', 'website', 'contact_person', 'business_email',
        'expected_monthly_spot_volume', 'expected_monthly_futures_volume', 'expected_assets_under_custody',
        'team_size', 'intended_products', 'api_requirements', 'market_making_interest', 'otc_interest',
        'fiat_requirements', 'subaccount_requirements', 'state', 'kyb_status', 'compliance_status',
        'risk_rating', 'recommended_by_admin_id', 'approved_by_admin_id', 'approved_at', 'metadata',
    ];

    protected $casts = [
        'intended_products' => 'array',
        'api_requirements' => 'array',
        'market_making_interest' => 'boolean',
        'otc_interest' => 'boolean',
        'fiat_requirements' => 'array',
        'subaccount_requirements' => 'array',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
