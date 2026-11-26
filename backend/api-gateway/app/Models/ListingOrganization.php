<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingOrganization extends Model
{
    protected $fillable = ['organization_uuid', 'owner_user_id', 'legal_name', 'project_name', 'jurisdiction', 'website', 'business_email', 'registration_details', 'incorporation_date', 'registered_address', 'project_category', 'primary_contact', 'authorized_representative', 'status', 'trading_name', 'organization_type', 'operating_country', 'registered_country', 'business_description', 'business_information', 'regulatory_information', 'ownership_structure', 'kyb_status', 'kyb_progress', 'kyb_current_stage', 'kyb_submitted_at', 'kyb_reviewed_at', 'registry_verification_status', 'screening_status'];

    protected $casts = ['registration_details' => 'array', 'registered_address' => 'array', 'primary_contact' => 'array', 'authorized_representative' => 'array', 'business_information' => 'array', 'regulatory_information' => 'array', 'ownership_structure' => 'array', 'incorporation_date' => 'date', 'kyb_submitted_at' => 'datetime', 'kyb_reviewed_at' => 'datetime'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ListingApplication::class, 'organization_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(ListingTeamMember::class, 'organization_id');
    }

    public function people(): HasMany { return $this->hasMany(ListingOrganizationPerson::class, 'organization_id'); }
    public function kybDocuments(): HasMany { return $this->hasMany(ListingOrganizationDocument::class, 'organization_id'); }
    public function declarations(): HasMany { return $this->hasMany(ListingOrganizationDeclaration::class, 'organization_id'); }
    public function invitations(): HasMany { return $this->hasMany(ListingOrganizationInvitation::class, 'organization_id'); }
    public function kybReviews(): HasMany { return $this->hasMany(ListingOrganizationKybReview::class, 'organization_id'); }
}
