<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionalMembership extends Model
{
    protected $fillable = ['membership_uuid', 'institution_id', 'user_id', 'role_id', 'status', 'permissions_override', 'invited_at', 'accepted_at'];

    protected $casts = ['permissions_override' => 'array', 'invited_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(InstitutionalAccount::class, 'institution_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(InstitutionalRole::class, 'role_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(InstitutionalMemberSubaccountPermission::class, 'membership_id');
    }
}
