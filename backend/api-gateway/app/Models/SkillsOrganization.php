<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillsOrganization extends Model
{
    protected $fillable = ['owner_user_id', 'name', 'country', 'industry', 'status', 'kyb_status', 'billing_status', 'plan_code', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function members(): HasMany
    {
        return $this->hasMany(SkillsOrganizationMember::class, 'organization_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SkillsBusinessSeat::class, 'organization_id');
    }
}
