<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsOrganizationMember extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'email', 'role', 'status', 'invited_at', 'accepted_at', 'expires_at'];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
