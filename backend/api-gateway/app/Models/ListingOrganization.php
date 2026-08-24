<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingOrganization extends Model
{
    protected $fillable = ['organization_uuid', 'owner_user_id', 'legal_name', 'project_name', 'jurisdiction', 'website', 'business_email', 'registration_details', 'incorporation_date', 'registered_address', 'project_category', 'primary_contact', 'authorized_representative', 'status'];

    protected $casts = ['registration_details' => 'array', 'registered_address' => 'array', 'primary_contact' => 'array', 'authorized_representative' => 'array', 'incorporation_date' => 'date'];

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
}
