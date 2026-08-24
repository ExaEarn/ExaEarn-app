<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingTeamMember extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'email', 'role', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
