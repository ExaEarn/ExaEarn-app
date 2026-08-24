<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ListingApplication extends Model
{
    protected $fillable = ['application_uuid', 'reference', 'organization_id', 'submitted_by', 'application_type', 'application_status', 'integration_status', 'progress_percent', 'project_information', 'asset_information', 'blockchain_information', 'tokenomics', 'technology', 'security', 'legal_compliance', 'market_community', 'liquidity', 'listing_request', 'verified_metadata', 'risk_flags', 'review_summary', 'submitted_at', 'recommended_by_admin_id', 'recommended_at', 'approved_by_admin_id', 'approved_at', 'rejected_by_admin_id', 'rejected_at', 'decision_reason', 'idempotency_key'];

    protected $casts = ['project_information' => 'array', 'asset_information' => 'array', 'blockchain_information' => 'array', 'tokenomics' => 'array', 'technology' => 'array', 'security' => 'array', 'legal_compliance' => 'array', 'market_community' => 'array', 'liquidity' => 'array', 'listing_request' => 'array', 'verified_metadata' => 'array', 'risk_flags' => 'array', 'review_summary' => 'array', 'submitted_at' => 'datetime', 'recommended_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(ListingOrganization::class, 'organization_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ListingReview::class, 'application_id');
    }

    public function assetConfiguration(): HasOne
    {
        return $this->hasOne(ListingAssetConfiguration::class, 'application_id');
    }

    public function marketConfigurations(): HasMany
    {
        return $this->hasMany(ListingMarketConfiguration::class, 'application_id');
    }

    public function networkConfigurations(): HasMany
    {
        return $this->hasMany(ListingAssetNetworkConfiguration::class, 'application_id');
    }

    public function contractValidations(): HasMany
    {
        return $this->hasMany(ListingContractValidation::class, 'application_id');
    }

    public function launchEvents(): HasMany
    {
        return $this->hasMany(ListingLaunchEvent::class, 'application_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(ListingLaunchSchedule::class, 'application_id');
    }
}
